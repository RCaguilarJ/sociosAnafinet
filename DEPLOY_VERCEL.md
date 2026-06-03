# Deploy en Vercel

## Variables requeridas

- `DATABASE_URL` o en su defecto:
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `PUBLIC_APP_URL`

## Variables opcionales

- `BASE_URL`
- `UPLOADS_DIR`
- `SESSION_NAME`
- `SESSION_TTL`
- `MEMBERSHIP_FEE_AMOUNT`
- `MEMBERSHIP_FEE_CURRENCY`
- `MEMBERSHIP_FEE_LABEL`
- `PAYMENT_ADMIN_EMAIL`
- `MAIL_FROM_EMAIL`
- `MAIL_FROM_NAME`

## Variables de pago

### Mercado Pago

- `MERCADOPAGO_ACCESS_TOKEN`
- `MERCADOPAGO_PUBLIC_KEY`
- `MERCADOPAGO_WEBHOOK_SECRET`
- `MERCADOPAGO_USE_SANDBOX`

### PayPal

- `PAYPAL_CLIENT_ID`
- `PAYPAL_CLIENT_SECRET`
- `PAYPAL_USE_SANDBOX`

## Notas importantes

- La app ya queda preparada para ejecutar PHP en Vercel con `vercel-php`.
- Las sesiones ya no dependen del disco local: se guardan en MySQL en la tabla `app_sessions`.
- Los archivos subidos ya no se consumen como archivos publicos directos del proyecto: pasan por `media.php`.
- En Vercel, los uploads se guardan en almacenamiento temporal del runtime si no migras documentos e imagenes a un storage externo. Eso evita errores, pero no garantiza persistencia entre invocaciones. `UPLOADS_DIR` solo sirve si tu entorno ofrece una ruta persistente montada.
- El limite practico de carga para documentos se ajusto a 4 MB para mantenerse por debajo del limite de cuerpo de peticion de Vercel Functions.
- `PUBLIC_APP_URL` debe apuntar al dominio publico real para que los retornos de Mercado Pago y el flujo de alta con pagos funcionen correctamente.
- Si vas a enviar correos desde PHP con `mail()`, el entorno debe tener configurado un relay SMTP o un servicio compatible; de lo contrario los pagos seguiran funcionando pero los avisos por correo no saldran.
