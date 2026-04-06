# Deploy en Vercel

## Variables requeridas

- `DATABASE_URL` o en su defecto:
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`

## Variables opcionales

- `BASE_URL`
- `UPLOADS_DIR`
- `SESSION_NAME`
- `SESSION_TTL`

## Notas importantes

- La app ya queda preparada para ejecutar PHP en Vercel con `vercel-php`.
- Las sesiones ya no dependen del disco local: se guardan en MySQL en la tabla `app_sessions`.
- Los archivos subidos ya no se consumen como archivos publicos directos del proyecto: pasan por `media.php`.
- En Vercel, los uploads se guardan en almacenamiento temporal del runtime si no migras documentos e imagenes a un storage externo. Eso evita errores, pero no garantiza persistencia entre invocaciones. `UPLOADS_DIR` solo sirve si tu entorno ofrece una ruta persistente montada.
- El limite practico de carga para documentos se ajusto a 4 MB para mantenerse por debajo del limite de cuerpo de peticion de Vercel Functions.
