# GMPays WooCommerce Payment Gateway - Installation Guide

## 🌍 Language / Idioma
- [English](#english)
- [Español](#español)

---

## English

# Installation Guide for GMPays WooCommerce Gateway

## Prerequisites

Before installing the GMPays WooCommerce Gateway, ensure you have:

1. **WordPress 5.0+** installed
2. **WooCommerce 5.0+** installed and activated
3. **PHP 7.4+** or higher
4. **WooCommerce Multi Currency** plugin (recommended for non-USD stores)
5. **GMPays Account** with approved API credentials

## Step 1: Install Dependencies

The plugin uses the GMPays PHP SDK. Install it using Composer:

```bash
cd wp-content/plugins/gmpays-woocommerce-gateway
composer install
```

If you don't have Composer, download it from [getcomposer.org](https://getcomposer.org/).

## Step 2: Upload and Activate Plugin

### Method 1: Manual Upload
1. Upload the `gmpays-woocommerce-gateway` folder to `/wp-content/plugins/`
2. Go to **WordPress Admin → Plugins**
3. Find "GMPays WooCommerce Payment Gateway" and click **Activate**

### Method 2: ZIP Upload
1. Compress the plugin folder into a ZIP file
2. Go to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin** and select your ZIP file
4. Click **Install Now** and then **Activate**

## Step 3: Get GMPays Credentials

1. Log in to your [GMPays account](https://cp.gmpays.com)
2. Navigate to **API Settings** or **Integration**
3. Obtain the following credentials:
   - **Project ID**: Your unique project identifier
   - **API Key**: Your API authentication key
   - **HMAC Key**: Your signature verification key
4. Note the environment URLs:
   - **Test**: `https://checkout.pay.gmpays.com`
   - **Live**: `https://checkout.gmpays.com`

## Step 4: Configure the Plugin

1. Go to **WooCommerce → Settings → Payments**
2. Find **Credit Card (GMPays)** and click **Manage**
3. Configure the following settings:

### Basic Settings
- **Enable/Disable**: Check to enable the gateway
- **Title**: Display name at checkout (e.g., "Credit Card")
- **Description**: Payment method description for customers

### API Configuration
- **Test Mode**: Enable for testing (recommended initially)
- **Project ID**: Enter your GMPays Project ID
- **Test API Key**: Your sandbox API key
- **Test HMAC Key**: Your sandbox HMAC key
- **Live API Key**: Your production API key (when ready)
- **Live HMAC Key**: Your production HMAC key (when ready)

### Advanced Settings
- **Payment Action**: Choose "Capture" or "Authorize Only"
- **Debug Log**: Enable for troubleshooting

4. Click **Save changes**

## Step 5: Configure Webhooks

1. In your GMPays dashboard, go to **Webhooks** or **Notifications**
2. Add a new webhook with this URL:
   ```
   https://yourdomain.com/wp-json/gmpays/v1/webhook
   ```
3. Select the following events:
   - Payment Success
   - Payment Failed
   - Payment Cancelled
   - Refund Processed
4. Save the webhook configuration

## Step 6: Configure Currency (if not using USD)

Since GMPays processes payments in USD, you need to configure currency conversion:

### Using WooCommerce Multi Currency:
1. Install and activate **WooCommerce Multi Currency** plugin
2. Go to **WooCommerce → Multi Currency**
3. Add USD to your currency list
4. Set appropriate exchange rates
5. The GMPays gateway will automatically convert prices to USD

### Using USD as Base Currency:
1. Go to **WooCommerce → Settings → General**
2. Set **Currency** to "US Dollar (USD)"

## Step 7: Test the Integration

### Test Mode
1. Ensure **Test Mode** is enabled in gateway settings
2. Create a test order
3. Select "Credit Card (GMPays)" at checkout
4. Complete the test payment on GMPays payment page
5. Verify order status updates correctly

### Test Cards (if provided by GMPays)
Use GMPays test card numbers for testing different scenarios:
- Successful payment
- Failed payment
- 3D Secure authentication

## Step 8: Go Live

Once testing is complete:

1. Obtain live API credentials from GMPays
2. Disable **Test Mode** in plugin settings
3. Enter your **Live API Key** and **Live HMAC Key**
4. Update webhook URLs in GMPays dashboard if needed
5. Place a small real order to confirm everything works

## Troubleshooting

### Common Issues

**Gateway not appearing at checkout:**
- Verify WooCommerce is active
- Check API credentials are entered correctly
- Ensure at least one shipping zone is configured
- Check if currency is supported

**Payment failures:**
- Enable debug logging in plugin settings
- Check logs at **WooCommerce → Status → Logs**
- Look for "gmpays" log entries
- Verify webhook URL is accessible

**Currency conversion issues:**
- Ensure WooCommerce Multi Currency is properly configured
- Verify USD is in the currency list
- Check exchange rates are up to date

### Debug Mode

To enable detailed logging:
1. Go to gateway settings
2. Enable **Debug Log**
3. Reproduce the issue
4. Check logs at **WooCommerce → Status → Logs → gmpays**

## Support

For technical support:
- **Plugin Issues**: Contact ElGrupito support
- **GMPays API**: Contact GMPays support at support@gmpays.com
- **Documentation**: Visit [GMPays API Docs](https://cp.gmpays.com/apidoc)

---

## Español

# Guía de Instalación para GMPays WooCommerce Gateway

## Requisitos Previos

Antes de instalar GMPays WooCommerce Gateway, asegúrate de tener:

1. **WordPress 5.0+** instalado
2. **WooCommerce 5.0+** instalado y activado
3. **PHP 7.4+** o superior
4. **WooCommerce Multi Currency** plugin (recomendado para tiendas que no usan USD)
5. **Cuenta GMPays** con credenciales API aprobadas

## Paso 1: Instalar Dependencias

El plugin utiliza el SDK PHP de GMPays. Instálalo usando Composer:

```bash
cd wp-content/plugins/gmpays-woocommerce-gateway
composer install
```

Si no tienes Composer, descárgalo desde [getcomposer.org](https://getcomposer.org/).

## Paso 2: Subir y Activar el Plugin

### Método 1: Carga Manual
1. Sube la carpeta `gmpays-woocommerce-gateway` a `/wp-content/plugins/`
2. Ve a **Administrador WordPress → Plugins**
3. Encuentra "GMPays WooCommerce Payment Gateway" y haz clic en **Activar**

### Método 2: Carga ZIP
1. Comprime la carpeta del plugin en un archivo ZIP
2. Ve a **Administrador WordPress → Plugins → Añadir nuevo**
3. Haz clic en **Subir plugin** y selecciona tu archivo ZIP
4. Haz clic en **Instalar ahora** y luego **Activar**

## Paso 3: Obtener Credenciales de GMPays

1. Inicia sesión en tu [cuenta GMPays](https://cp.gmpays.com)
2. Navega a **Configuración API** o **Integración**
3. Obtén las siguientes credenciales:
   - **ID de Proyecto**: Tu identificador único de proyecto
   - **Clave API**: Tu clave de autenticación API
   - **Clave HMAC**: Tu clave de verificación de firma
4. Anota las URLs del entorno:
   - **Pruebas**: `https://checkout.pay.gmpays.com`
   - **Producción**: `https://checkout.gmpays.com`

## Paso 4: Configurar el Plugin

1. Ve a **WooCommerce → Ajustes → Pagos**
2. Encuentra **Tarjeta de Crédito (GMPays)** y haz clic en **Gestionar**
3. Configura los siguientes ajustes:

### Configuración Básica
- **Activar/Desactivar**: Marca para activar la pasarela
- **Título**: Nombre mostrado en el checkout (ej., "Tarjeta de Crédito")
- **Descripción**: Descripción del método de pago para clientes

### Configuración API
- **Modo de Prueba**: Activar para pruebas (recomendado inicialmente)
- **ID de Proyecto**: Ingresa tu ID de Proyecto GMPays
- **Clave API de Prueba**: Tu clave API sandbox
- **Clave HMAC de Prueba**: Tu clave HMAC sandbox
- **Clave API en Vivo**: Tu clave API de producción (cuando estés listo)
- **Clave HMAC en Vivo**: Tu clave HMAC de producción (cuando estés listo)

### Configuración Avanzada
- **Acción de Pago**: Elige "Capturar" o "Solo Autorizar"
- **Registro de Depuración**: Activar para solución de problemas

4. Haz clic en **Guardar cambios**

## Paso 5: Configurar Webhooks

1. En tu panel de GMPays, ve a **Webhooks** o **Notificaciones**
2. Añade un nuevo webhook con esta URL:
   ```
   https://tudominio.com/wp-json/gmpays/v1/webhook
   ```
3. Selecciona los siguientes eventos:
   - Pago Exitoso
   - Pago Fallido
   - Pago Cancelado
   - Reembolso Procesado
4. Guarda la configuración del webhook

## Paso 6: Configurar Moneda (si no usas USD)

Como GMPays procesa pagos en USD, necesitas configurar la conversión de moneda:

### Usando WooCommerce Multi Currency:
1. Instala y activa el plugin **WooCommerce Multi Currency**
2. Ve a **WooCommerce → Multi Currency**
3. Añade USD a tu lista de monedas
4. Establece las tasas de cambio apropiadas
5. La pasarela GMPays convertirá automáticamente los precios a USD

### Usando USD como Moneda Base:
1. Ve a **WooCommerce → Ajustes → General**
2. Establece **Moneda** en "Dólar estadounidense (USD)"

## Paso 7: Probar la Integración

### Modo de Prueba
1. Asegúrate de que el **Modo de Prueba** esté activado en la configuración
2. Crea un pedido de prueba
3. Selecciona "Tarjeta de Crédito (GMPays)" en el checkout
4. Completa el pago de prueba en la página de pago de GMPays
5. Verifica que el estado del pedido se actualice correctamente

### Tarjetas de Prueba (si GMPays las proporciona)
Usa los números de tarjeta de prueba de GMPays para diferentes escenarios:
- Pago exitoso
- Pago fallido
- Autenticación 3D Secure

## Paso 8: Poner en Producción

Una vez completadas las pruebas:

1. Obtén credenciales de producción de GMPays
2. Desactiva el **Modo de Prueba** en la configuración del plugin
3. Ingresa tu **Clave API en Vivo** y **Clave HMAC en Vivo**
4. Actualiza las URLs de webhook en el panel de GMPays si es necesario
5. Realiza un pedido real pequeño para confirmar que todo funciona

## Solución de Problemas

### Problemas Comunes

**La pasarela no aparece en el checkout:**
- Verifica que WooCommerce esté activo
- Comprueba que las credenciales API estén ingresadas correctamente
- Asegúrate de tener al menos una zona de envío configurada
- Verifica si la moneda es compatible

**Fallos en el pago:**
- Activa el registro de depuración en la configuración del plugin
- Revisa los registros en **WooCommerce → Estado → Registros**
- Busca entradas de registro "gmpays"
- Verifica que la URL del webhook sea accesible

**Problemas de conversión de moneda:**
- Asegúrate de que WooCommerce Multi Currency esté configurado correctamente
- Verifica que USD esté en la lista de monedas
- Comprueba que las tasas de cambio estén actualizadas

### Modo de Depuración

Para activar el registro detallado:
1. Ve a la configuración de la pasarela
2. Activa **Registro de Depuración**
3. Reproduce el problema
4. Revisa los registros en **WooCommerce → Estado → Registros → gmpays**

## Soporte

Para soporte técnico:
- **Problemas del Plugin**: Contacta al soporte de ElGrupito
- **API de GMPays**: Contacta al soporte de GMPays en support@gmpays.com
- **Documentación**: Visita [Documentación API GMPays](https://cp.gmpays.com/apidoc)
