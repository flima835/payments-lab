# ATENCAO: ajuste "project_id" (e o repoURL em argocd/application.yaml) antes do apply.

variable "project_id" {
  description = "GCP project ID onde o cluster do lab sera criado"
  type        = string
  default     = "brb-payments-lab" # <-- SUBSTITUA pelo seu project ID real
}

variable "region" {
  default = "us-central1"
}

variable "zone" {
  default = "us-central1-a"
}

variable "zabbix_source_ip" {
  description = "IP publico (ou /32) do Zabbix Server, usado para restringir o LoadBalancer da porta 8889"
  type        = string
  default     = "0.0.0.0/32" # <-- SUBSTITUA pelo IP real do Zabbix antes do apply
}

provider "google" {
  project = var.project_id
  region  = var.region
}

resource "google_container_cluster" "primary" {
  name     = "payments-lab-cluster"
  location = var.zone

  deletion_protection = false

  initial_node_count = 1
  node_config {
    machine_type = "e2-medium"
  }
}

# Regra de firewall para o Collector alcancar o nginx externo (vm-lab-brb-nginx)
# via IP interno da VPC, na porta 80 (stub_status).
resource "google_compute_firewall" "allow_gke_to_nginx_vm" {
  name    = "allow-gke-to-brb-nginx"
  network = "default" # ajuste se sua VPC tiver outro nome
  project = var.project_id

  direction = "INGRESS"
  priority  = 1000

  allow {
    protocol = "tcp"
    ports    = ["80"]
  }

  # Range de IPs internos dos pods/nos do GKE - ajuste conforme o CIDR real do seu cluster
  source_ranges = ["10.0.0.0/8"]
  target_tags   = ["http-server"] # mesma tag que ja esta na vm-lab-brb-nginx
}
