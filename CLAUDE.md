# CLAUDE.md — recovery-business-hub

PHP/MySQL directory app for recovery businesses (views/index.php, views/add-business.php). Built for Hostinger hosting (config.php expects Hostinger MySQL).

Global rules: C:\Vaults\TechDad-Vault\CLAUDE.md. Env facts: C:\Vaults\TechDad-Vault\06-Reference\machine-env.md.

## Rules

- If wired to Hostinger git deploy, pushing main = production. Load deploy-verify before shipping. Hostinger deploy is additive: deleted files keep serving until removed by hand.
- config.php in the repo holds placeholder creds only. Real DB creds go in a config above webroot, never committed.
- Brand context: C:\Vaults\TechDad-Vault\02-Brands\Recovery-Business-Hub\.
- This tree can be behind origin (other sessions push). `git pull` before editing.
