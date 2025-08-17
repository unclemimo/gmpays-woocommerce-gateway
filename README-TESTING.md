# GMPays WooCommerce Gateway - Testing Guide

## Problema Resuelto

El plugin tenía un problema crítico donde **los hooks de WooCommerce no se ejecutaban** cuando los usuarios regresaban del gateway de GMPays, causando que las órdenes no se actualizaran correctamente.

## Solución Implementada

### 1. **Hooks de Inicialización Temprana**
Se implementaron hooks que se ejecutan **antes** de que WooCommerce procese la página:

- `init` (prioridad 5) - Hook más temprano posible
- `wp` (prioridad 5) - Después de WordPress pero antes del template
- `template_redirect` (prioridad 5) - Antes de cargar el template

### 2. **Manejo Centralizado de Retornos**
Se creó un método central `process_return_parameters()` que maneja todos los tipos de retorno:

- **Éxito**: `gmpays_success=1&order_id=XXXX`
- **Fallo**: `gmpays_failure=1&order_id=XXXX`
- **Cancelación**: `gmpays_cancelled=1&order_id=XXXX`

### 3. **Procesamiento Inmediato**
Los retornos se procesan **inmediatamente** cuando se detectan los parámetros, no esperando a que WooCommerce cargue la página.

## Cómo Probar

### Paso 1: Verificar que el Plugin Esté Activo
```bash
# En el admin de WordPress, ir a Plugins
# Verificar que "GMPays WooCommerce Payment Gateway" esté activo
```

### Paso 2: Crear una Orden de Prueba
1. Ir al checkout
2. Completar una orden con el método GMPays
3. Anotar el ID de la orden

### Paso 3: Simular Retorno de GMPays
Usar una de estas URLs para simular el retorno:

#### Para Cancelación:
```
https://tu-sitio.com/carrito/?gmpays_cancelled=1&order_id=XXXX
```

#### Para Fallo:
```
https://tu-sitio.com/carrito/?gmpays_failure=1&order_id=XXXX&reason=Test%20failure
```

#### Para Éxito:
```
https://tu-sitio.com/carrito/?gmpays_success=1&order_id=XXXX&transaction_id=TEST123
```

### Paso 4: Verificar Logs
Los logs deberían mostrar:

```
GMPays DEBUG: handle_early_returns called on init hook
GMPays DEBUG: GMPays return parameters detected in early returns
GMPays DEBUG: $_GET parameters: Array ( [gmpays_cancelled] => 1 [order_id] => XXXX )
GMPays DEBUG: process_return_parameters called
GMPays DEBUG: Processing cancelled return for order XXXX
GMPays DEBUG: Order retrieved for cancellation return - Order ID: XXXX
GMPays DEBUG: Order status update result: SUCCESS
GMPays DEBUG: Cancelled return processing completed for order: XXXX
```

### Paso 5: Verificar Cambios en la Orden
1. Ir al admin de WordPress
2. Buscar la orden por ID
3. Verificar que el estado haya cambiado:
   - **Cancelación**: Estado → "Cancelado"
   - **Fallo**: Estado → "Fallido"
   - **Éxito**: Estado → "En espera"

## Archivos Modificados

### `class-wc-gateway-gmpays-credit-card.php`
- ✅ Agregados hooks de inicialización temprana
- ✅ Implementado `process_return_parameters()`
- ✅ Implementados métodos de procesamiento específicos
- ✅ Eliminados métodos obsoletos

### `gmpays-woocommerce-gateway.php`
- ✅ Sin cambios (ya estaba correcto)

## Verificación de Funcionamiento

### Antes de la Solución:
- ❌ Los hooks se registraban pero nunca se ejecutaban
- ❌ Las órdenes no se actualizaban
- ❌ No había logs de debugging

### Después de la Solución:
- ✅ Los hooks se ejecutan en el momento correcto
- ✅ Las órdenes se actualizan inmediatamente
- ✅ Logs extensivos para debugging
- ✅ Redirecciones limpias (sin parámetros en URL)

## Troubleshooting

### Si los logs no aparecen:
1. Verificar que el plugin esté activo
2. Verificar que el debug esté habilitado en la configuración
3. Verificar permisos de escritura en el directorio de logs

### Si las órdenes no se actualizan:
1. Verificar que el ID de la orden sea correcto
2. Verificar que el método de pago sea `gmpays_credit_card`
3. Verificar que no haya errores en los logs de PHP

### Si hay errores de redirección:
1. Verificar que las funciones de WooCommerce estén disponibles
2. Verificar que no haya conflictos con otros plugins
3. Verificar la configuración de permalinks

## Notas Importantes

- **Los hooks se ejecutan en prioridad 5** para asegurar que se ejecuten antes que otros plugins
- **El procesamiento es inmediato** para evitar problemas de timing
- **Se mantiene compatibilidad** con el hook `woocommerce_thankyou` para casos especiales
- **Los logs son extensivos** para facilitar el debugging en producción

## Próximos Pasos

1. **Probar en entorno de staging** antes de producción
2. **Monitorear logs** durante las primeras transacciones reales
3. **Verificar que no haya conflictos** con otros plugins de WooCommerce
4. **Considerar agregar métricas** para monitorear el éxito de los retornos
