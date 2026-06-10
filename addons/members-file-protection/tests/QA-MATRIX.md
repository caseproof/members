# Manual QA Matrix — Members File Protection

| Environment | Expected |
|-------------|----------|
| Apache 2.4 + mod_rewrite | `.htaccess` auto-written; Test passes |
| Apache 2.4 without mod_rewrite | Manual notice; Test fails until rules added |
| LiteSpeed | Treated as Apache; `.htaccess` rules apply |
| Nginx + manual location block | Test passes after config applied |
| Nginx without manual config | Notice on settings page; files accessible |
| Plain permalinks | Persistent admin error notice; protection inactive |
| Cloudflare proxy | No redirect loop on 404 behavior |
| Large file (>100MB) | Chunked streaming completes |
| manage_options user, no matching role | File served (bypass) |
| WP Offload Media active | Warning notice about local-only protection |
| Loopback blocked host | Nginx Test may fail; use manual confirmation |
