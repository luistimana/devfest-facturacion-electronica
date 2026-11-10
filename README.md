# DevFest Piura — Facturación electrónica

## Sobre el proyecto

Este proyecto nace como una demostración para DevFest Piura: una aplicación web para administrar productos, registrar ventas y mostrar el flujo de emisión de comprobantes electrónicos en Perú.

Su propósito es educativo y práctico. Permite recorrer la configuración del emisor, preparar facturas y boletas, generar documentos XML UBL 2.1, firmarlos digitalmente y comunicarse directamente con SUNAT desde los servicios de la aplicación. Incluye una interfaz sencilla para consultar ventas, estados y representaciones impresas.

La aplicación está desarrollada con Laravel 12, PHP 8.5 y MySQL. El entorno BETA sirve para pruebas; antes de emitir comprobantes reales se deben configurar las credenciales SOL y el certificado digital del emisor, y validar el flujo conforme a los requisitos vigentes de SUNAT.

## Funcionalidades

- Inicio de sesión y configuración inicial del emisor.
- Administración de productos y servicios.
- Registro de facturas y boletas, con cálculo de IGV al 18 %.
- Generación de XML UBL 2.1 y firma digital.
- Envío individual a SUNAT mediante SOAP y `sendBill`.
- Lectura de respuestas y CDR.
- Representación impresa en PDF y ticket de 80 mm.
- Consulta del estado de los comprobantes enviados anteriormente mediante ticket, cuando corresponda.

## Requisitos

- PHP 8.5 con las extensiones `dom`, `openssl`, `zip` y `pdo_mysql` habilitadas.
- Composer 2.
- Node.js y npm.
- MySQL.

## Instalación local

Crea la base de datos MySQL `devfest_facturacion_electronica` y configura las variables de conexión en `.env`. Luego ejecuta:

```powershell
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan db:seed
php artisan serve
```

El seeder crea el usuario y dos productos de muestra; no registra ventas. Credenciales locales: `admin@devfest.test` / `123456789`.

Abre `http://devfest-facturacion-electronica.test/` si usas Laragon, o `http://127.0.0.1:8000/` con `artisan serve`.

## Configuración SUNAT

Completa los datos del emisor y el entorno desde **Configuración**. El código del local anexo tiene como valor inicial `0000`. En BETA, la aplicación usa las credenciales de prueba `MODDATOS` y permite preparar un certificado demo para firmar documentos de prueba. Para producción, configura las credenciales SOL y carga el certificado digital real asociado al RUC emisor.

La configuración de SUNAT se guarda cifrada en MySQL y el certificado se almacena en una ubicación privada. El usuario SOL se ingresa sin anteponer nuevamente el RUC. No uses credenciales ni certificados reales en pruebas.

## Estructura principal

- `app/Http/Controllers`: controladores de ventas, productos y configuración.
- `app/Models`: modelos de usuarios, productos, comprobantes y configuración SUNAT.
- `app/Services/Sunat`: generación, firma, envío SOAP y procesamiento de respuestas.
- `app/Sunat/Templates/invoice.php`: plantilla PHP para construir el XML UBL 2.1 de facturas y boletas.
- `resources/views`: pantallas de la aplicación y formatos de representación.
- `database/migrations`: estructura de la base de datos.

La integración debe validarse en el entorno de pruebas de SUNAT antes de utilizarse en producción.

## Prueba del bot de Telegram

1. Crea un bot con [@BotFather](https://t.me/BotFather) y copia su token directamente en `TOKEN_TLG` dentro de `.env` (no lo compartas ni lo subas a Git).
2. En una terminal, desde la carpeta del proyecto, ejecuta `php artisan telegram:poll`.
3. Abre el chat con tu bot y envíale `/start`. El bot responderá con el ID de ese chat.
4. Copia ese ID en `TELEGRAM_CHAT_ID` en `.env`, detén el comando con `Ctrl+C` y vuelve a ejecutar `php artisan telegram:poll`.
5. Envía `/cmds` para ver los comandos disponibles o `/ventas_hoy` para consultar el número de comprobantes y el total registrado hoy.

El bot solo comparte datos de ventas con el ID configurado. La primera respuesta de `/start` muestra únicamente el ID necesario para configurar el acceso.

### Comandos propuestos para ampliar el bot

Comandos implementados:

- `/cmds` — muestra el resumen de comandos disponibles, con ejemplos.
- `/ventas_hoy` — total y cantidad de comprobantes registrados hoy.
- `/ventas_semana` — total y cantidad de comprobantes desde el lunes.
- `/ventas_mes` — total y cantidad de comprobantes del mes.
- `/ultimas_ventas` — comprobantes con serie, número, cliente, total y estado, en páginas de cinco.
- `/estado F001-25` — busca un comprobante por serie y número.
- `/clientes_mes` — muestra los cinco clientes con mayor monto facturado este mes.
- `/productos` — productos más vendidos del mes por unidades.

Al enviar `/cmds`, el bot muestra un teclado persistente con los comandos en dos columnas. Toca una opción para ejecutar la consulta. El botón `/estado` pide la serie y el número en un mensaje aparte; también se puede consultar directamente con `/estado F001-25`. Si el bot ya estaba corriendo, reinicia `php artisan telegram:poll` para cargar los cambios.

Las respuestas presentan títulos y cifras importantes en negrita para facilitar su lectura en Telegram.

En `/ultimas_ventas` y `/estado`, cada comprobante incluye botones para recibir su PDF o XML firmado directamente en Telegram. La lista usa flechas para avanzar y retroceder entre páginas de cinco. El PDF se genera al solicitarlo; el XML se puede compartir después de que el sistema lo haya firmado y guardado.
