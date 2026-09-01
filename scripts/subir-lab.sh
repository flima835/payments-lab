#!/bin/bash
# Adaptado de Twitter-Clone/scripts/subir-lab.sh para o payments-lab.
# Fluxo em 2 fases (Terraform p/ infra + este script p/ o resto) de proposito:
# GKE + provider kubernetes num unico "terraform apply" cria dependencia
# circular (o cluster precisa existir antes do provider k8s funcionar) e e
# a causa mais comum de apply quebrado num lab. Este caminho e mais rapido
# de fato funcionar "de cara".
set -e

GREEN='\033[1;32m'; YELLOW='\033[1;33m'; NC='\033[0m'

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PROJECT_ID="SEU_PROJECT_ID"     # <-- AJUSTE (igual ao infra/terraform/main.tf)
ZONE="us-central1-a"
CLUSTER_NAME="payments-lab-cluster"

echo -e "${GREEN}### 1. Infraestrutura (Terraform) ###${NC}"
cd infra/terraform
terraform init
terraform apply -auto-approve
cd - > /dev/null

echo -e "${GREEN}### 2. Contexto do kubectl ###${NC}"
gcloud container clusters get-credentials $CLUSTER_NAME --zone $ZONE --project $PROJECT_ID

echo -e "${GREEN}### 3. Secret do Datadog (precisa existir em 2 namespaces) ###${NC}"
if [ -z "${DD_API_KEY:-}" ]; then
  echo -e "${YELLOW}DD_API_KEY nao setado. Rode: export DD_API_KEY=<sua-chave> e execute o script de novo.${NC}"
  exit 1
fi
kubectl create ns datadog --dry-run=client -o yaml | kubectl apply -f -
kubectl create secret generic datadog-secret --from-literal api-key=$DD_API_KEY -n datadog --dry-run=client -o yaml | kubectl apply -f -
kubectl create secret generic datadog-secret --from-literal api-key=$DD_API_KEY -n default --dry-run=client -o yaml | kubectl apply -f -

echo -e "${GREEN}### 4. ArgoCD ###${NC}"
kubectl get ns argocd >/dev/null 2>&1 || kubectl create ns argocd
kubectl apply -n argocd -f https://raw.githubusercontent.com/argoproj/argo-cd/stable/manifests/install.yaml
kubectl -n argocd wait --for=condition=available --timeout=300s deployment/argocd-repo-server deployment/argocd-server || true
kubectl apply -f argocd/project.yaml
kubectl apply -f argocd/application.yaml

echo -e "${GREEN}### 5. Build da imagem (Cloud Build) - UMA UNICA tag ###${NC}"
TAG=$(date +%Y%m%d-%H%M%S)
IMAGE_URL="us-central1-docker.pkg.dev/$PROJECT_ID/repo-payments/app:$TAG"
(cd app && gcloud builds submit --tag $IMAGE_URL .)

echo -e "${GREEN}### 6. Atualiza manifesto e deixa o ArgoCD sincronizar ###${NC}"
sed -i "s|image: .*repo-payments/app:.*|image: $IMAGE_URL|" k8s/base/deployment.yaml
git add k8s/base/deployment.yaml
git commit -m "lab: atualiza imagem para $TAG" || echo -e "${YELLOW}Nada novo para commitar.${NC}"
if git push; then
  echo "Push feito. ArgoCD vai sincronizar sozinho."
else
  echo -e "${YELLOW}git push falhou - aplicando via kubectl como fallback...${NC}"
  kubectl apply -k k8s/base
fi
kubectl -n argocd annotate application payments-lab argocd.argoproj.io/refresh=hard --overwrite || true

echo -e "${GREEN}### 7. Banco de dados ###${NC}"
kubectl wait --for=condition=ready pod -l app=mysql --timeout=120s
sleep 10
POD_MYSQL=$(kubectl get pods -l app=mysql -o jsonpath="{.items[0].metadata.name}")
kubectl exec -i $POD_MYSQL -- mysql -u root payments_lab < app/db/schema.sql

echo -e "${GREEN}### 8. IPs externos (app + metrics para Zabbix) ###${NC}"
echo -e "${YELLOW}Ctrl+C quando os EXTERNAL-IP aparecerem.${NC}"
kubectl get service payments-service otel-collector-metrics -w
