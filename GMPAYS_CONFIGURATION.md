# GMPays WooCommerce Gateway - Configuración

## URLs de Configuración en GMPays

Para que el gateway funcione correctamente, debes configurar las siguientes URLs en tu panel de control de GMPays:

### 1. URL de Éxito (Success URL)
```
https://tudominio.com/checkout/order-received/[ORDER_ID]/?key=[ORDER_KEY]
```
**Nota:** GMPays reemplazará automáticamente `[ORDER_ID]` y `[ORDER_KEY]` con los valores reales.

### 2. URL de Fallo (Failure URL)
```
https://tudominio.com/carrito/?gmpays_failure=1&order_id=[ORDER_ID]
```

### 3. URL de Cancelación (Cancel URL)
```
https://tudominio.com/carrito/?gmpays_cancelled=1&order_id=[ORDER_ID]
```

### 4. URL de Notificación (Webhook URL)
```
https://tudominio.com/wp-json/gmpays/v1/webhook
```

## Parámetros de Retorno

### Pago Exitoso
Cuando un pago es exitoso, GMPays redirige al cliente con estos parámetros:
- `gmpays_success=1`
- `order_id=[ID_DE_LA_ORDEN]`
- `transaction_id=[ID_DE_TRANSACCION]` (opcional)
- `amount=[MONTO]` (opcional)
- `currency=[MONEDA]` (opcional)
- `invoice=[ID_DE_FACTURA]` (opcional)

### Pago Fallido
Cuando un pago falla, GMPays redirige al cliente con estos parámetros:
- `gmpays_failure=1`
- `order_id=[ID_DE_LA_ORDEN]`
- `reason=[RAZON_DEL_FALLO]` (opcional)
- `invoice_id=[ID_DE_FACTURA]` (opcional)
- `invoice=[ID_DE_FACTURA]` (opcional)

### Pago Cancelado
Cuando un pago es cancelado, GMPays redirige al cliente con estos parámetros:
- `gmpays_cancelled=1`
- `order_id=[ID_DE_LA_ORDEN]`
- `invoice=[ID_DE_FACTURA]` (opcional)

## Estados de las Órdenes

### Pago Exitoso
- **Estado:** `on-hold` (en espera de confirmación)
- **Nota:** "Payment received via GMPays - Order placed on hold for confirmation"
- **Acción:** El administrador debe revisar y cambiar manualmente a "processing" o "completed"

### Pago Fallido
- **Estado:** `failed`
- **Nota:** "Payment failed via GMPays: [RAZON]"
- **Acción:** Los productos se restauran automáticamente al carrito

### Pago Cancelado
- **Estado:** `cancelled`
- **Nota:** "Payment cancelled by customer via GMPays"
- **Acción:** Los productos se restauran automáticamente al carrito

## Webhooks

### Estructura de Notificación
GMPays envía notificaciones webhook con la siguiente estructura:

```json
{
  "state": "success",
  "project": "123456",
  "invoice": "7238479374",
  "status": "Paid",
  "amount": "200.45",
  "net_amount": "2",
  "recieved_amount": "195.3",
  "rate": "0.010",
  "currency_project": "RUB",
  "currency_user": "USD",
  "user": "9336353",
  "type": "yandex",
  "wallet": "88326736363",
  "comment": "User 9336353 invoice",
  "project_invoice": "1541586969",
  "time": "1472620176",
  "signature": "[FIRMA_RSA]"
}
```

### Estados de Pago Soportados
- `New` - Pago creado
- `Processing` - Pago en procesamiento
- `Paid` - Pago completado
- `Refused` - Pago rechazado
- `Refund` - Pago reembolsado

## Solución de Problemas

### Problema: Las órdenes no se marcan como "on-hold"
**Solución:** Verifica que la URL del webhook esté configurada correctamente en GMPays y que el servidor pueda recibir notificaciones POST.

### Problema: Los clientes no son redirigidos a la página correcta
**Solución:** Verifica que las URLs de éxito, fallo y cancelación estén configuradas correctamente en GMPays.

### Problema: Los webhooks no se procesan
**Solución:** Verifica los logs de WooCommerce en WooCommerce → Estado → Logs → Buscar logs de 'gmpays-webhook'.

## Notas Importantes

1. **Verificación de Firma:** Todos los webhooks son verificados usando el certificado RSA de GMPays.
2. **Manejo de Errores:** Si un webhook falla, GMPays lo reintentará durante 24 horas.
3. **Duplicados:** GMPays puede enviar notificaciones duplicadas. El sistema está diseñado para manejar esto de forma segura.
4. **Monedas:** Todas las transacciones se procesan en USD en GMPays, independientemente de la moneda de tu tienda.
