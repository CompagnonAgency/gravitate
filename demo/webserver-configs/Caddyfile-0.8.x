# Caddyfile for Caddy 0.8.x and below

:8080
gzip
fastcgi / 127.0.0.1:9000 php

# Begin - Security
# deny all direct access for these folders
rewrite {
    r       /(.git|cache|bin|logs|backups|tests)/.*$
    status  403
}
# deny running scripts inside core system folders
rewrite {
    r       /(system|vendor)/.*\.(txt|xml|md|html|yaml|php|pl|py|cgi|twig|sh|bat)$
    status  403
}
# deny running scripts inside user folder
rewrite {
    r       /user/.*\.(txt|md|yaml|php|pl|py|cgi|twig|sh|bat)$
    status  403
}
# deny access to specific files in the root folder
rewrite {
    r       /(LICENSE.txt|composer.lock|composer.json|nginx.conf|web.config|htaccess.txt|\.htaccess)
    status  403
}
## End - Security

# global rewrite should come last.
rewrite {
    to  {path} {path}/ /index.php?_url={uri}
}
