# payments-lab

Simulador de pagamentos bancários com telemetria unificada (OpenTelemetry Collector
como hub central), monitorado por **Zabbix** (pull via Prometheus exporter), com
traces/métricas em **Google Cloud Trace/Monitoring** e **Datadog**, provisionado em
**GKE** via **Terraform**, com deploy contínuo por **ArgoCD**.

Modelado a partir do repositório [Twitter-Clone](https://github.com/flima835/Twitter-Clone)
do mesmo autor, trocando a aplicação por um simulador de pagamentos e adicionando um
OTel Collector próprio como ponto único de saída de telemetria.

## Arquitetura

```
payments-app (PHP) --OTLP HTTP--> otel-collector --+--> Prometheus :8889 --> Zabbix (PULL)
     |                                              +--> googlecloud exporter --> Cloud Trace/Monitoring
     +-- /metrics.php --(scrape)--> otel-collector  +--> datadog exporter --> Datadog
vm-lab-brb-nginx --(scrape stub_status)--> otel-collector
```

- **payments-app**: PHP + MySQL, tela única de simulação de transferência bancária.
  Cada simulação gera um span OTLP (`otel_helper.php`, sem SDK/Composer) e incrementa
  contadores expostos em `/metrics.php`.
- **otel-collector**: recebe OTLP do app, raspa `/metrics.php` do app e o `stub_status`
  do nginx externo (`vm-lab-brb-nginx`), e reexporta para Zabbix (Prometheus `:8889`),
  Cloud Trace/Monitoring e Datadog.
- **Zabbix**: consome `http://<IP>:8889/metrics` via HTTP Agent (item mestre) +
  preprocessing nativo (JavaScript + Prometheus Pattern) — sem scripts externos.
- **ArgoCD**: sincroniza `k8s/base` continuamente (self-heal automático).
- **Datadog Operator**: reaproveitado do Twitter-Clone (`datadog-agent.yaml`).

## Estrutura do repositório

```
app/                       Código PHP da aplicação (simulador de pagamentos)
  index.php                 Dashboard + formulário de simulação
  processa_pagamento.php    Grava o pagamento e envia o trace OTLP
  metrics.php                Endpoint Prometheus com contadores de negócio
  otel_helper.php             Helper OTLP/HTTP sem dependências externas
  conexao_banco.php          Conexão MySQL
  Dockerfile
  db/schema.sql              Schema da tabela `pagamentos`
infra/
  terraform/main.tf          Cluster GKE + firewall p/ alcançar o nginx externo
  otel/otel-collector-config.yaml   Config "fonte" do Collector (referência/edição)
k8s/base/
  deployment.yaml            Deployment + Service do payments-app
  mysql-deployment.yaml      Deployment + Service do MySQL
  otel-collector.yaml        Deployment + 2 Services do Collector (interno + :8889 externo)
  otel-collector-configmap.yaml  ConfigMap gerado a partir de infra/otel/otel-collector-config.yaml
  datadog-agent.yaml         DatadogAgent CRD (reaproveitado do Twitter-Clone)
  kustomization.yaml
argocd/
  application.yaml            Application do ArgoCD (aponta pra este repo, path k8s/base)
  project.yaml                 AppProject do ArgoCD
scripts/subir-lab.sh          Terraform apply + credenciais + secrets + ArgoCD + build + deploy + banco
zabbix_template_otel.json     Template pronto para importar no Zabbix
GUIA-payments-lab.md          Decisões de design, correções feitas e passo a passo detalhado
```

## Pré-requisitos

- Projeto GCP com faturamento ativo e APIs de GKE/Cloud Build/Artifact Registry habilitadas.
- `gcloud`, `kubectl`, `terraform` instalados (ou use o Cloud Shell, que já tem tudo).
- Uma API Key do Datadog (Organization Settings → API Keys).
- IP público do seu Zabbix Server.

## Ajustes obrigatórios antes de rodar

| Arquivo | O quê ajustar |
|---|---|
| `infra/terraform/main.tf` | `var.project_id` (seu project GCP real) |
| `k8s/base/otel-collector.yaml` | `loadBalancerSourceRanges` (IP do Zabbix) |
| `infra/otel/otel-collector-config.yaml` | IP do nginx externo, se for diferente de `10.128.0.2` |

## Quick start

```bash
git clone https://github.com/flima835/payments-lab.git
cd payments-lab
export DD_API_KEY=<sua-chave-do-datadog>
bash scripts/subir-lab.sh
```

O script cuida de: `terraform apply` → credenciais do `kubectl` → secrets do Datadog
(namespaces `default` e `datadog`) → instalação do ArgoCD → build da imagem (Cloud
Build, tag única) → deploy → restauração do schema no MySQL → mostra os dois
`EXTERNAL-IP` (app e métricas para o Zabbix).

## Importar no Zabbix

1. **Data collection → Templates → Import** → `zabbix_template_otel.json`.
2. Vincule o template ao host e configure `{$OTEL.HOST}` = IP do Service
   `otel-collector-metrics`.
3. **Test** no item `otel.metrics.raw` para validar o preprocessing.

Detalhes de decisões de design, o que foi corrigido em relação às versões anteriores
dos arquivos, e o passo a passo completo estão em `GUIA-payments-lab.md`.

## Limitações conhecidas

- Sem Grafana/Prometheus "de base" incluídos aqui — se você já tem um Prometheus
  rodando em outro lugar do seu ambiente, aponte o `scrape_configs` dele para
  `otel-collector-metrics:8889` (mesmo endpoint usado pelo Zabbix).
- `MYSQL_ALLOW_EMPTY_PASSWORD=yes` e `MYSQL_ROOT_PASSWORD=""` são apenas para lab —
  não usar essa configuração fora de ambiente de teste.
