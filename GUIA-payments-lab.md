# payments-lab — simulador de pagamentos com telemetria (Zabbix + Cloud Trace + Datadog)

Modelado no seu repo `Twitter-Clone` (Terraform + GKE + ArgoCD + Datadog), trocando a
aplicação e adicionando um **OTel Collector próprio** como hub central de telemetria.

```
payments-app (PHP) --OTLP HTTP--> otel-collector --+--> Prometheus :8889 --> Zabbix (PULL)
     |                                              +--> googlecloud exporter --> Cloud Trace/Monitoring
     +-- /metrics.php --(scrape)--> otel-collector  +--> datadog exporter --> Datadog
vm-lab-brb-nginx --(scrape stub_status)--> otel-collector
```

---

## 1. O que eu corrigi nos arquivos que você tinha alterado

- **`zabbix_template_otel.json` — bug estrutural**: seu `"triggers"` estava como irmão de
  `"templates"` no JSON raiz. No formato de export do Zabbix, `triggers` precisa estar
  **dentro** de cada objeto de template (junto de `items`), senão o import ignora os
  triggers (ou falha, dependendo da versão). Corrigido — os 3 triggers originais + 1 novo
  agora estão aninhados corretamente.
- **`PROMETHEUS_PATTERN` com `function`/`sum`**: sua alteração de
  `sum(otelcol_exporter_sent_spans_total)` (sintaxe PromQL, que o Zabbix não interpreta)
  para `["otelcol_exporter_sent_spans_total", "function", "sum"]` está **certa** — é assim
  que a agregação funciona nativamente no preprocessing do Zabbix. Mantive esse padrão em
  todos os itens novos.
- **Receiver `nginx` no `otel-collector-config.yaml`**: seu `endpoint: "http://localhost/nginx_status"`
  só funcionaria se o nginx rodasse no mesmo host/pod do Collector. Como o Collector vai
  rodar dentro do GKE e o nginx é a VM externa da sua imagem (`vm-lab-brb-nginx`,
  IP interno `10.128.0.2`), troquei para `http://10.128.0.2/nginx_status` — confirme que
  o módulo `stub_status` está habilitado nessa VM (`nginx -T | grep -A2 stub_status`).

## 2. Simplificações que fiz para funcionar "de cara"

- **Removi o Datadog PHP Tracer do `Dockerfile`** (o `curl -LO ... datadog-setup.php`).
  Era um segundo caminho de instrumentação e uma dependência de rede no build. Agora só
  existe **um** caminho: `otel_helper.php` manda o span via OTLP/HTTP direto pro Collector,
  que já encaminha pra Datadog. Menos coisa pra quebrar no `gcloud builds submit`.
- **Um único build, uma única tag** no script (o passo que você colou fazia
  `gcloud builds submit` duas vezes — uma com `$TAG` e outra com `v11` — build em dobro
  sem necessidade).
- **Não recriei o `mysqld-exporter`** que estava em `k8s/experiments/mysql-zabbix.yaml`.
  Ele não é necessário pro que o Zabbix está monitorando agora (Collector + app). Dá pra
  mesclar depois se você quiser métricas de MySQL também.
- **Service `otel-collector-metrics` do tipo `LoadBalancer` com `loadBalancerSourceRanges`**
  restrito ao IP do Zabbix. Isso substitui o passo manual de
  `gcloud compute firewall-rules create` — o GKE cria a regra de firewall sozinho a partir
  do Service. Um passo a menos pra você rodar.
- **Duas fases (`terraform apply` + script) em vez de uma só.** Você pediu "a partir de um
  apply subir toda a operação" — tecnicamente dá pra fazer isso com o provider
  `kubernetes`/`helm` do Terraform, mas GKE + recursos Kubernetes no mesmo apply é a causa
  nº 1 de "apply quebrado" em labs (o provider k8s precisa que o cluster já exista, o que
  cria dependência circular e normalmente exige `-target` ou dois applies mesmo assim). Pra
  você rodar isso rápido **agora**, `scripts/subir-lab.sh` faz: `terraform apply` →
  credenciais → secret do Datadog → ArgoCD → build → deploy → banco → IPs, tudo num
  comando só, sem a fragilidade do apply único.

## 3. Antes de rodar — 4 coisas que só você sabe

| O quê | Onde | Ação |
|---|---|---|
| Project ID do GCP | `infra/terraform/main.tf` (`var.project_id`) | Substituir `brb-payments-lab` pelo seu project real |
| IP do Zabbix Server | `k8s/base/otel-collector.yaml` (`loadBalancerSourceRanges`) | Substituir `IP_DO_ZABBIX/32` |
| Repo Git deste projeto | `argocd/application.yaml` e `argocd/project.yaml` | Suba esta pasta pra um repo (ex.: `payments-lab`) e ajuste `repoURL` |
| Chave da API do Datadog | variável de ambiente | `export DD_API_KEY=<sua-chave>` antes de rodar o script (Organization Settings → API Keys no Datadog) |

## 4. Passo a passo (equivalente aos passos que você colou, adaptados)

```bash
# 1) Suba a pasta payments-lab pro seu GitHub (repo novo ou branch novo)
git init && git add . && git commit -m "payments-lab inicial"
git remote add origin https://github.com/SEU_USUARIO/payments-lab.git
git push -u origin main

# 2) Exporte a chave do Datadog
export DD_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# 3) Rode tudo de uma vez
bash scripts/subir-lab.sh
```

O script já cobre o que antes eram os passos 1–5 manuais do Twitter-Clone (terraform apply,
`get-credentials`, build, `kubectl apply`, restaurar schema). No final ele fica em watch
mostrando os dois `EXTERNAL-IP`: um do `payments-service` (a tela de simulação) e outro do
`otel-collector-metrics` (o que você usa no `{$OTEL.HOST}` do Zabbix).

## 5. Importar/validar no Zabbix (resumo — guia completo já entregue antes)

1. **Data collection → Templates → Import** → `zabbix_template_otel.json`.
2. Vincule o template ao host, configure `{$OTEL.HOST}` = IP do `otel-collector-metrics`.
3. **Test** no item `otel.metrics.raw` pra confirmar que o JS de validação passa.
4. Abra `http://<IP_payments-service>` e faça 2–3 simulações — isso já popula
   `payments_total`/`payments_error_total` no `/metrics.php`, que o Collector rapa e
   propaga pros dependentes `payments.*` no Zabbix.

## 6. Coisas que fiquei sem visibilidade (não inventei nada aqui)

- **Grafana e Prometheus "de base"** que você pediu pra manter: não vieram no
  `Twitter-Clone-master.zip` (só tinha ArgoCD + Datadog Operator). Se eles já existem em
  outro repo/Helm release do seu ambiente, o único ajuste necessário é apontar o
  `scrape_configs` do seu Prometheus existente pro Service `otel-collector-metrics:8889`
  (mesmo endpoint que o Zabbix usa) — me manda a config atual do seu Prometheus/Grafana
  que eu ajusto certinho.
- Não tenho como confirmar se `10.128.0.2` está na mesma VPC/CIDR que o GKE vai usar —
  ajuste o `source_ranges` do `google_compute_firewall.allow_gke_to_nginx_vm` em
  `main.tf` pro CIDR real do seu cluster (`gcloud container clusters describe` mostra o
  `clusterIpv4Cidr`).
