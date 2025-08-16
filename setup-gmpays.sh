#!/bin/bash

# GMPays WooCommerce Plugin Setup Script
# Este script te ayudará a generar las llaves RSA necesarias

echo "==========================================="
echo "GMPays WooCommerce Plugin - Setup Script"
echo "==========================================="
echo ""

# Verificar si OpenSSL está instalado
if ! command -v openssl &> /dev/null; then
    echo "ERROR: OpenSSL no está instalado. Por favor instálalo primero."
    exit 1
fi

# Crear directorio para las llaves
KEYS_DIR="gmpays-keys"
mkdir -p "$KEYS_DIR"
cd "$KEYS_DIR"

echo "Generando par de llaves RSA..."
echo ""

# Generar llave privada
openssl genrsa -out private_key.pem 2048
if [ $? -ne 0 ]; then
    echo "ERROR: No se pudo generar la llave privada"
    exit 1
fi

# Extraer llave pública
openssl rsa -in private_key.pem -pubout -out public_key.pem
if [ $? -ne 0 ]; then
    echo "ERROR: No se pudo extraer la llave pública"
    exit 1
fi

echo ""
echo "✅ Llaves generadas exitosamente!"
echo ""
echo "==========================================="
echo "INSTRUCCIONES DE CONFIGURACIÓN"
echo "==========================================="
echo ""
echo "📁 Las llaves se han guardado en el directorio: $KEYS_DIR/"
echo ""
echo "PASO 1: Configurar la llave pública en GMPays"
echo "----------------------------------------------"
echo "1. Inicia sesión en tu panel de GMPays: https://cp.gmpays.com"
echo "2. Ve a la sección de Signatures: https://cp.gmpays.com/project/sign"
echo "3. En el campo 'Public key', pega el siguiente contenido:"
echo ""
echo "--- INICIO DE LA LLAVE PÚBLICA (copiar todo incluyendo las líneas BEGIN/END) ---"
cat public_key.pem
echo "--- FIN DE LA LLAVE PÚBLICA ---"
echo ""
echo "4. Guarda los cambios en GMPays"
echo ""
echo "PASO 2: Configurar el plugin de WooCommerce"
echo "--------------------------------------------"
echo "1. Ve a WooCommerce > Settings > Payments > GMPays Credit Card"
echo "2. Completa los siguientes campos:"
echo ""
echo "   API URL: (revisa tu panel de GMPays, generalmente es https://paygate.gamemoney.com)"
echo "   Project ID: [Tu ID de proyecto de GMPays, ej: 603]"
echo ""
echo "3. En el campo 'RSA Private Key', pega el siguiente contenido:"
echo ""
echo "--- INICIO DE LA LLAVE PRIVADA (copiar todo incluyendo las líneas BEGIN/END) ---"
cat private_key.pem
echo "--- FIN DE LA LLAVE PRIVADA ---"
echo ""
echo "PASO 3: Configurar las URLs en GMPays"
echo "--------------------------------------"
echo "En tu panel de GMPays, configura estas URLs:"
echo ""
echo "Success URL: https://tudominio.com/checkout/order-received/"
echo "Failure URL: https://tudominio.com/checkout/"
echo "Notification URL: https://tudominio.com/wp-json/gmpays/v1/webhook"
echo ""
echo "⚠️  IMPORTANTE: Reemplaza 'tudominio.com' con tu dominio real"
echo ""
echo "==========================================="
echo "SEGURIDAD"
echo "==========================================="
echo ""
echo "🔒 MANTÉN TU LLAVE PRIVADA SEGURA!"
echo "   - No la compartas con nadie"
echo "   - No la subas a repositorios públicos"
echo "   - Guarda una copia de respaldo en un lugar seguro"
echo ""
echo "📝 Se ha creado un archivo de respaldo con las llaves en:"
echo "   $PWD/"
echo ""
echo "==========================================="
echo "¿Necesitas ayuda? Contacta soporte técnico"
echo "==========================================="