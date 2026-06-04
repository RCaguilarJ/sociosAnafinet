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
- `MAIL_TRANSPORT`
- `MAIL_FROM_EMAIL`
- `MAIL_FROM_NAME`
- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_SECURE`
- `SMTP_AUTH`
- `SMTP_USERNAME`
- `SMTP_PASSWORD`
- `SMTP_TIMEOUT`
- `SMTP_VERIFY_PEER`

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
- Si `MAIL_TRANSPORT=smtp`, la app enviara los correos directamente por SMTP usando `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USERNAME` y `SMTP_PASSWORD`.
- `MAIL_FROM_EMAIL` define el remitente visible para el usuario. Lo normal es que coincida con `SMTP_USERNAME` o con un alias permitido por tu proveedor SMTP.
- Si usas `SMTP_SECURE=tls`, el puerto habitual es `587`. Para conexion SSL implicita usa `SMTP_SECURE=ssl` con puerto `465`.
- Si el certificado del servidor SMTP no valida correctamente en un entorno local, puedes usar `SMTP_VERIFY_PEER=0` solo para pruebas. En produccion debe mantenerse en `1`.
- Si `MAIL_TRANSPORT=mail`, la app conserva el comportamiento anterior y usara `mail()` de PHP.
