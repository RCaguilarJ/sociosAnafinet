Usa esta carpeta solo como ubicacion temporal de importacion.

Mejor practica:

- Coloca el archivo aqui solo mientras ejecutas la importacion.
- Despues de importar, muevelo fuera de `www` o eliminalo.
- Para un flujo mas seguro, usa una ruta explicita fuera del web root.

Comandos:

```powershell
php .\scripts\import_wp_users.php
php .\scripts\import_wp_users.php "C:\ruta-segura\wp_users (1).sql"
```
