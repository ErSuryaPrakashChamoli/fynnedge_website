---
paths:
  - docker/nginx/nginx.conf
---

# Docker Nginx

## nginx answers only fynnedge.com, www.fynnedge.com and localhost
Owner decision INF-04 (2026-11-26): a default_server returns 444 for any other Host, because Laravel builds some links (password resets, other modules' emails) from the Host header. Allowed: fynnedge.com, www.fynnedge.com, localhost, 127.0.0.1. A load balancer or uptime check that hits the container by IP must send Host: fynnedge.com. Add any new public hostname here before pointing DNS at it. Validate with nginx -t in staging (no nginx on the dev machine).
