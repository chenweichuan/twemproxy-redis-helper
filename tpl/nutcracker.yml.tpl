r{{NUMBER}}:
  listen: {{IP}}:8001
  hash: fnv1a_64
  distribution: ketama
  redis: true
  auto_eject_hosts: true
  server_retry_timeout: 2000
  server_failure_limit: 3
  timeout: 2000
  servers:
{{SERVERS}}

rw{{NUMBER}}:
  listen: {{IP}}:8002
  hash: fnv1a_64
  distribution: ketama
  redis: true
  auto_eject_hosts: false
  timeout: 2000
  servers:
{{SERVERS}}
