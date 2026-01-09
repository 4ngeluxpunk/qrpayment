# Módulo de Pagos y Transferencias QR para PrestaShop

Este módulo permite integrar pagos mediante códigos QR y transferencias bancarias manuales de forma profesional en tu tienda PrestaShop. Es ideal para billeteras digitales (como Yape, Plin, PayPal, etc.) o transferencias bancarias tradicionales, facilitando la validación del pago mediante la carga de comprobantes (vouchers).

## Características Principales

* **Gestión de Múltiples Apps:** Permite configurar ilimitadas aplicaciones de pago o cuentas bancarias desde el panel de administración.
* **Experiencia de Usuario Fluida (Modal):** El proceso de pago se realiza en una ventana modal moderna dentro del checkout, sin recargar la página innecesariamente.
* **Códigos QR y Datos de Cuenta:** Muestra el código QR escaneable y permite copiar el número de teléfono o cuenta con un solo clic.
* **Subida de Comprobantes (Voucher):** El cliente debe adjuntar una captura de pantalla o foto del pago para finalizar el pedido.
* **Seguridad:** Validación estricta de archivos subidos (solo imágenes JPG, PNG, GIF) para evitar vulnerabilidades.
* **Gestión de Pedidos:** Los pedidos se crean con un estado personalizado ("En espera de validación QR") para que el administrador verifique el pago antes de aprobarlo.
* **Visualización en Admin:** El comprobante de pago adjunto se muestra directamente en el detalle del pedido en el Back Office.

## Requisitos

* PrestaShop 1.7.x, 8.x o superior.
* PHP compatible con tu versión de PrestaShop.

## Instalación

1.  Descarga el archivo `.zip` del módulo.
2.  Ve a tu panel de administración de PrestaShop -> **Módulos** -> **Gestor de Módulos**.
3.  Haz clic en **Subir un módulo** y selecciona el archivo `.zip`.
4.  Una vez instalado, haz clic en **Configurar**.

## Configuración

### Configuración General
Desde la configuración principal del módulo puedes definir:
* Título del método de pago (ej. "Pago con QR / Transferencia").
* Descripción corta para el checkout.
* Estados de pedido para éxito o error.

### Gestión de Cuentas (Apps)
Dentro de la configuración, accede al botón **ADMINISTRAR APPS Y CUENTAS** para:
* Crear nuevas opciones de pago (ej. Banco X, Billetera Y).
* Subir el logo de la app y la imagen del código QR.
* Establecer números de teléfono/cuenta.
* Definir montos máximos permitidos por app.
* Ordenar la posición de las opciones.

## Uso (Flujo del Cliente)

1.  En el checkout, el cliente selecciona la opción de pago QR.
2.  Se abre una ventana modal donde elige la app con la que desea pagar.
3.  El cliente escanea el QR o copia el número y realiza el pago desde su celular.
4.  El cliente hace clic en "Continuar" y sube la captura de pantalla del pago.
5.  El pedido se confirma y queda en espera de validación manual.

## Contribuir

Si deseas contribuir a este proyecto, por favor crea un *Pull Request* o abre un *Issue* para discutir los cambios propuestos.

## Licencia

[Tu Licencia Aquí, ej: AFL 3.0, MIT, o Propietaria]
