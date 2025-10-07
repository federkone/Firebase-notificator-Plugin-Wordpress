# Complemento WordPress - Firebase Notifications para WooCommerce

## Objetivo

Este complemento para WordPress tiene como objetivo integrar **Firebase** como servicio de notificación para productos nuevos publicados a través de **WooCommerce**.

## Dependencias

### Google API PHP Client
- **Paquete**: `google/apiclient`
- **Versión**: 2.16.0
- **Fuente**: [GitHub Release v2.16.0](https://github.com/googleapis/google-api-php-client/releases/tag/v2.16.0)
- **Instalación**:
  ```bash
  composer require google/apiclient:^2.16.0
  ```
  
## Flujo de Trabajo Actual

El sistema de notificaciones sigue este flujo:

```
📦 Nuevo producto agregado en categoría "ofertas" 
    │
    ↓
🔍 Detectar evento de producto nuevo
    │
    ↓
📱 Usar servicio de Google Firebase para notificar el evento
    │
    ↓
⚙️ Firebase procesa el evento según la configuración en la plataforma de Google
```

## Características Técnicas

### Configuración Actual
- **Datos para api de firebase** en 'service-account.json'
- **Categoría específica**: "ofertas"

## Personalización

El sistema de notificaciones puede ser **adaptado a sus propias necesidades**, permitiendo:

- ✅ Modificar los eventos que disparan notificaciones
- ✅ Cambiar la categoría objetivo
- ✅ Personalizar el payload enviado a Firebase
- ✅ Configurar diferentes flujos de trabajo

### Plan de Mejoras

El código puede mejorarse trasladando las configuraciones a un panel administrativo para permitir leer los datos desde el frontend de Wordpress

### Posibles Mejoras

1. **Panel de administración** en WordPress
2. **Configuración dinámica** desde el backend
3. **Autonomía completa** del plugin
5. **Logs y monitoreo** de eventos

## Requisitos

- WordPress con WooCommerce activado
- Cuenta de Google Firebase
- Credenciales de Firebase configuradas
