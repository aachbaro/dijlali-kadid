#!/usr/bin/env python3
"""Active le plugin djilali-stripe-checkout dans WordPress via MySQL."""
import subprocess, re

CONTAINER = "djilali-kadid-db-1"

def mysql_read(sql):
    r = subprocess.run(
        ["docker", "exec", CONTAINER, "mysql",
         "-uwordpress", "-pDji1ali_WP_2026!", "--default-character-set=utf8mb4",
         "wordpress", "-sN", "-e", sql],
        capture_output=True, text=True
    )
    return r.stdout.strip()

def mysql_write(sql):
    r = subprocess.run(
        ["docker", "exec", "-i", CONTAINER, "mysql",
         "-uwordpress", "-pDji1ali_WP_2026!", "--default-character-set=utf8mb4",
         "wordpress"],
        input=sql + "\n", capture_output=True, text=True
    )
    return r.returncode == 0

cur = mysql_read('SELECT option_value FROM wp_options WHERE option_name="active_plugins"')
plugin = "djilali-stripe-checkout/djilali-stripe-checkout.php"

if plugin in cur:
    print("Déjà actif")
else:
    count = int(re.search(r"^a:(\d+):", cur).group(1))
    new_entry = 'i:{};s:{}:"{}";'.format(count, len(plugin), plugin)
    new_val = re.sub(r"^a:\d+:", "a:{}:".format(count + 1), cur)
    new_val = new_val[:-1] + new_entry + "}"
    escaped = new_val.replace("\\", "\\\\").replace('"', '\\"')
    ok = mysql_write('UPDATE wp_options SET option_value="{}" WHERE option_name="active_plugins"'.format(escaped))
    print("Plugin activé" if ok else "ERREUR activation")

mysql_write('DELETE FROM wp_options WHERE option_name="rewrite_rules"')
print("Rewrite rules flushées")
