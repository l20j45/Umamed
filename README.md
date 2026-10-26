# Umamed

Sistema de seguimiento clínico para pacientes de una clínica de bariatría: alta de
pacientes, expediente con medidas antropométricas (peso, IMC y perímetros) y portal
del paciente con historial y fotos de progreso.

## Stack

| Capa | Tecnología |
| --- | --- |
| Backend | Laravel 13.38 / PHP ^8.3 |
| Base de datos | SQLite (WAL) |
| Frontend | Inertia.js 2 + React 18 + TypeScript + Tailwind CSS |
| Rutas en JS | Ziggy (`route()`) |
| Roles y permisos | spatie/laravel-permission 8 |
| Lector de logs | opcodesio/log-viewer |
| Pruebas | Pest 4 sobre PHPUnit 12 |
| Estilo PHP | Laravel Pint |
| Traducciones | laravel-lang (es publicado en `lang/es`) |

## Requisitos

- PHP 8.3 o superior con `pdo_sqlite`, `mbstring`, `openssl` y `gd`
- Composer 2
- Node.js 20 o superior

## Instalación local

```bash
composer install
cp .env.example .env          # ajusta lo que necesites
php artisan key:generate
touch storage/app/umamed.sqlite   # base SQLite (una sola vez)
php artisan migrate --seed
npm install
npm run build                 # o `npm run dev` mientras desarrollas
php artisan serve
```

`migrate --seed` crea los roles/permisos y cuatro usuarios de demostración
(la contraseña se controla con `SEEDER_PASSWORD`, por defecto `password123`):

| Usuario | Correo | Rol |
| --- | --- | --- |
| Dr. Alejandro Rojas | bariatra@sistema.com | bariatra |
| Secretaria Ana | recepcion@sistema.com | recepcionista |
| Admin Sistema | admin@sistema.com | admin |
| yazmin | yazmin@usuario.com | paciente |

## Base de datos

La aplicación usa **SQLite** en modo WAL (`storage/app/umamed.sqlite`), con
`busy_timeout` y `synchronous = NORMAL` configurados en `config/database.php`.
Para el volumen de esta clínica —una doctora, recepción y dos workers de cola
sobre un solo servidor— SQLite evita mantener un contenedor de base de datos,
hace los respaldos triviales (un archivo) y es el motor que Laravel soporta de
primera mano.

> Sobre **Turso**: es SQLite (libSQL) hospedado y tiene sentido cuando necesitas
> réplicas en varias regiones o acceso desde funciones serverless. Aquí no
> aporta: PHP no trae cliente libSQL (habría que compilar una extensión),
> agregarías latencia de red a cada consulta y perderías la copia de respaldo
> trivial. Si algún día hay app móvil con backend propio, Turso se puede
> reevaluar como réplica de lectura.

### Traer los datos que ya tienes en MySQL

Con la conexión `mysql` todavía configurada en el `.env` original:

```bash
php artisan db:migrar-a-sqlite --desde=mysql --fresh
```

Crea el archivo destino, aplica todas las migraciones y copia tabla por tabla
(sin la tabla `migrations`), todo dentro de una sola corrida. Al terminar imprime
las dos líneas que deben quedar en el `.env`:

```env
DB_CONNECTION=sqlite
DB_DATABASE=storage/app/umamed.sqlite
```

Si sólo quieres el esquema sin datos, agrega `--sin-datos`.

### Respaldos

```bash
php artisan respaldo:crear                # base + fotos en storage/app/backups
php artisan respaldo:crear --conservar=14 # conserva los últimos 14
php artisan respaldo:crear --sin-fotos    # sólo la base
```

Cada corrida crea `storage/app/backups/<fecha>/` con la copia del archivo SQLite
(haciendo antes un `wal_checkpoint`) y las carpetas de fotos y avatares. Hay una
tarea programada diaria a las 02:00 hora de la clínica que conserva los últimos 7.

Como los respaldos viven dentro del mismo volumen, **cópialos fuera del servidor**
(rsync, S3, otro disco). `.env.production.example` deja `AWS_*` listo si quieres
empujarlos a S3.

## Auditoría

Cada alta, cambio o borrado de citas, mediciones, fotos y usuarios queda en la
tabla `audit_logs` con el usuario, la IP, el modelo afectado y el detalle de los
campos modificados (`antes → después`). Las contraseñas y tokens nunca se
guardan.

Los gestores se registran con `#[ObservedBy(AuditObserver::class)]` en los
modelos, así que cualquier cambio hecho por código o por la interfaz queda
registrado.

### Vista de actividad

El administrador tiene **Actividad** en el menú (`/actividad`), con:

- filtros por acción (alta/cambio/baja), tipo de registro, usuario y rango de fechas;
- referencia legible de cada registro (por ejemplo “Cita del 23/11/2026 10:00”);
- detalle desplegable con cada campo y su valor anterior y nuevo;
- los registros ya eliminados se marcan como “(ya no existe)”;
- paginación de 50 en 50.

Para consultarla por consola: `App\Models\AuditLog::latest('id')->limit(20)->get()`.

## Roles

- **admin**: todo (alta de pacientes, mediciones, configuración).
- **bariatra**: expide y registra mediciones del expediente.
- **recepcionista**: consulta expedientes y agenda.
- **paciente**: sólo su portal (`/mi-progreso`), no ve datos de otros pacientes.

El **registro público está deshabilitado**: no existe `/register` y las cuentas
las crea el personal desde el panel (`/admin/users/create`). Así nadie puede
darse de alta por su cuenta y quedar con acceso al portal del paciente.

## Fotos de progreso

Cada foto que sube un paciente se reescribe antes de guardarse
([`App\Support\OptimizadorDeFotos`](app/Support/OptimizadorDeFotos.php)):

- se convierte a JPEG, lo que **elimina los metadatos EXIF** (GPS, fecha y modelo
  del teléfono) que traen las fotos de celular;
- se corrige la orientación según el EXIF antes de descartarlo;
- se limita el lado mayor a `CLINIC_FOTO_LADO_MAXIMO` píxeles (1600 por defecto)
  con calidad `CLINIC_FOTO_CALIDAD_JPEG` (85 por defecto).

Al eliminar una foto se borra también el archivo del disco, no sólo el registro.

## Expediente del paciente

Desde `/pacientes/{id}` el personal clínico puede:

- capturar una medición nueva (el IMC se calcula con la estatura del paciente);
- **corregir** una medición mal capturada: el botón "Editar" precarga el
  formulario y al guardar recalcula el IMC;
- **eliminar** una medición capturada por error (queda registrado en la auditoría);
- capturar o corregir la **estatura** del paciente, que es la base del IMC;
- **descargar el expediente en PDF**, con los datos del paciente, el resumen
  (peso inicial, actual, cambio total e IMC), el historial completo de mediciones
  y las últimas seis fotos de progreso reducidas para que el archivo no pese de
  más ([`pdf/expediente.blade.php`](resources/views/pdf/expediente.blade.php)).

## Agenda de citas

La doctora (rol `bariatra`), administración y recepción trabajan en `/agenda`:

- vista mensual con navegación entre meses y filtro por doctor (la recepcionista
  y el admin ven la agenda de todos; cada bariatra sólo la suya);
- alta, edición, cambio de estado, cancelación y eliminación de citas desde un modal;
- estados: pendiente, confirmada, atendida, cancelada y no asistió;
- se pueden bloquear horarios dejando el paciente vacío;
- **no se permiten citas encimadas** para el mismo doctor (validado en el
  servidor y otra vez dentro de una transacción que bloquea la agenda del
  doctor, para que dos personas no aparten el mismo horario a la vez), salvo
  que la cita existente esté cancelada o marcada como no asistió.

**Cancelar no es borrar**: el botón "Cancelar" deja la cita en estado
`cancelada` y la conserva en el historial del paciente; borrarla
definitivamente sólo lo puede hacer el rol `admin`.

Las horas se capturan y se muestran en la zona de la clínica
(`CLINIC_TIMEZONE`, por defecto `America/Mexico_City`) y se guardan en UTC.

El paciente ve sus propias citas en `/mis-citas` (no puede modificarlas ni ver
las de otros).

### Resumen diario

`php artisan citas:resumen-diario` envía por correo a cada doctor el listado de
sus citas del día (hora, paciente, tipo y estado). Se programa a diario a la
hora de `CLINIC_HORA_RESUMEN` (07:00 por defecto) en la zona de la clínica, y
se puede lanzar a mano para otra fecha:

```bash
php artisan citas:resumen-diario --fecha=2026-11-10
```

## Cobros e ingresos

El consultorio **registra** lo que se cobra; no se procesan pagos en línea.

### Catálogo por doctor

Cada doctor mantiene su propio catálogo en `/catalogo` con nombre, descripción,
tipo (`consulta`, `producto`, `tratamiento`, `otro`) y costo. Los conceptos se
pueden **desactivar** cuando dejan de ofrecerse: dejan de aparecer al cobrar pero
se conservan para el historial.

- El bariatra administra sólo su catálogo.
- Administración ve y edita todos.
- Recepción puede consultarlos (los necesita para cobrar) pero no modificarlos.

### Cobro de una cita

Desde la agenda, cada cita tiene un botón **Cobro** (`/agenda/{cita}/cobro`)
donde se agregan los conceptos que se llevó el paciente, con cantidad. El total
se calcula solo y la cita se marca como **pagada** (con fecha y método: efectivo,
transferencia, tarjeta u otro) o queda **pendiente**.

Cada cargo guarda una copia del nombre y del precio del momento, así que si el
catálogo cambia o el concepto se elimina, el historial de lo cobrado no se altera.

### Ingresos

`/ingresos` muestra por mes (y por doctor, para administración): **facturado**,
**cobrado**, **pendiente**, número de citas con cobro, desglose por tipo de
concepto y el detalle cita por cita con su estado de pago.

## WhatsApp

Cada cita de la agenda ofrece dos caminos:

1. **Botón verde "WhatsApp"**: abre `wa.me` con el mensaje de recordatorio ya
   escrito para que la doctora o recepción lo envíe desde su teléfono. Funciona
   sin configurar nada.
2. **Envío automático**: si se configuran las credenciales de la API oficial
   (WhatsApp Cloud de Meta), la app envía el mensaje desde el servidor y además
   aparece el botón "Enviar recordatorio".

Para activar el envío automático en `.env`:

```env
WHATSAPP_DRIVER=cloud
WHATSAPP_TOKEN=...            # token permanente (usuario de sistema)
WHATSAPP_PHONE_NUMBER_ID=...  # id del número en el panel de Meta
WHATSAPP_PAIS=52
WHATSAPP_HORAS_ANTES=24
WHATSAPP_RECORDATORIOS=true   # se activa solo si el driver es cloud
```

Con `WHATSAPP_DRIVER=log` (por defecto) los mensajes sólo se escriben en el log:
sirve para desarrollo y no requiere credenciales.

### Plantilla aprobada (necesaria para los recordatorios)

Meta sólo acepta **texto libre** dentro de las 24 h posteriores al último
mensaje del paciente. Un recordatorio que sale antes de la cita casi siempre
cae fuera de esa ventana, así que para el envío automático hay que registrar
una plantilla en el panel de Meta y configurarla:

```env
WHATSAPP_PLANTILLA=recordatorio_cita
WHATSAPP_PLANTILLA_IDIOMA=es_MX
```

La plantilla debe tener **tres variables en el cuerpo, en este orden**:
`{{1}}` nombre del paciente, `{{2}}` fecha y `{{3}}` hora. Si `WHATSAPP_PLANTILLA`
queda vacía, la app manda texto libre y Meta puede rechazar el mensaje; el botón
`wa.me` sigue funcionando siempre porque lo envía una persona.

En lugar de crearla a mano en el panel de Meta, la app puede darla de alta:

```bash
php artisan whatsapp:plantilla --crear   # la envía a revisión
php artisan whatsapp:plantilla           # consulta si ya fue aprobada
```

`--crear` envía nombre, idioma, categoría (`WHATSAPP_PLANTILLA_CATEGORIA`,
`UTILITY` por defecto), el cuerpo con las tres variables y los valores de ejemplo
que Meta pide para revisarla. Necesita `WHATSAPP_WABA_ID` (WhatsApp Business
Account ID, visible en el panel de Meta junto al número). El comando valida que
el cuerpo tenga exactamente tres variables antes de gastar la llamada.

### Recordatorios automáticos

`php artisan citas:recordatorios` corre **cada 15 minutos** (definido en
`routes/console.php`) y encola el aviso de las citas que empiezan dentro de la
ventana configurada (24 h antes, con 15 minutos de margen). En Docker lo dispara
el programa `laravel-scheduler` de `supervisord.conf`; fuera de Docker hace falta
un cron que ejecute `php artisan schedule:run` cada minuto.

El comando se puede probar a mano:

```bash
php artisan citas:recordatorios            # respeta la ventana de 24 h
php artisan citas:recordatorios --probar   # encola todas las citas futuras
```

Cada envío queda marcado en `recordatorio_enviado_en`; si la cita se mueve de
horario, el recordatorio vuelve a quedar pendiente.

### El paciente decide

En `/mis-citas` cada paciente puede desactivar los recordatorios por WhatsApp.
Si los desactiva, ni el proceso automático ni el botón manual le envían nada
(la agenda avisa al intentarlo).

### Aviso de fallos

Si un job agota sus reintentos (por ejemplo un recordatorio rechazado por Meta),
se registra en el log y, si `ADMIN_EMAIL` está configurado, se envía un correo
con el trabajo, la cola y el error.

## Diseño

La interfaz sigue una línea editorial de consultorio: papel cálido, tipografía
con serifa para títulos y verde pino como único acento.

| Elemento | Definición |
| --- | --- |
| Fondo / texto | `paper` `#F7F5F0` · `ink` `#1C1B18` |
| Primario | `pine` (verde pino apagado, `pine-700` para botones) |
| Bordes | `sand` `#E4DED2` |
| Acento cálido | `arcilla` (avisos y acciones destructivas) |
| Tipografías | **Lora** (títulos, `font-display`) e **Inter** (interfaz) |
| Radios | 2–4 px: esquinas casi rectas, sin tarjetas “infladas” |
| Sombras | Casi planas; la jerarquía se marca con bordes, no con sombras |

Los estilos base viven en [`resources/css/app.css`](resources/css/app.css)
(`.card`, `.field`, `.label`, `.btn-primary`, `.eyebrow`, `.page-header`) y los
tokens en [`tailwind.config.js`](tailwind.config.js). Ahí también se reasignaron
`emerald`, `blue`, `indigo` y `sky` al verde pino, y `purple` a arcilla, para que
cualquier pantalla nueva quede dentro de la misma familia por defecto.

Reglas al agregar pantallas: títulos de página en `h2` (toman la serifa solos por
`.page-header`), etiquetas en mayúsculas con `.eyebrow`, formularios con `.field`
y `.label`, acciones primarias con `.btn-primary`, y **nada de emojis** en la
interfaz.

El modo oscuro queda desactivado por defecto (`darkMode: 'class'`): el consultorio
trabaja siempre en claro y las clases `dark:` existentes no se aplican.

## Pruebas

```bash
php artisan test          # suite completa (usa SQLite en memoria)
./vendor/bin/pint         # formatea el código PHP
```

## Despliegue con Docker

El `Dockerfile` construye una imagen con nginx + php-fpm + supervisor, dos workers
de cola y el planificador de tareas. Los datos de entorno se toman de `.env`
mediante `env_file` (el archivo `.env` nunca se copia dentro de la imagen, ver
`.dockerignore`).

```bash
./deploy.sh        # Linux/macOS
./deploy.ps1       # Windows (podman)
./build-and-push.ps1   # construye y publica la imagen en Docker Hub
```

### Configuración de producción

`.env.production.example` es una plantilla lista para el servidor:
`APP_ENV=production`, `APP_DEBUG=false`, cookies `Secure`, log diario y correo
real. Cópiala como `.env` y genera la llave:

```bash
cp .env.production.example .env
php artisan key:generate
```

> ⚠️ No despliegues con el `.env` de desarrollo: viene con `APP_ENV=local` y
> `APP_DEBUG=true`, así que cualquier error mostraría trazas con credenciales y
> rutas del servidor en el navegador.

> ℹ️ El `docker-compose.yml` ya no levanta MySQL: la base es el archivo SQLite del
> volumen `storage_dataUmamed`. Si en el servidor quedó el volumen `mysql_dataUmamed`
> con datos, **exporta antes de borrarlo** con `php artisan db:migrar-a-sqlite --desde=mysql`
> apuntando a ese servidor.

## Notas de la migración a Laravel 13

El proyecto partió de Laravel 11 y se actualizó a Laravel 13:

- `laravel/framework ^13.0`, `laravel/tinker ^3.0`, `pestphp/pest ^4.0`,
  `phpunit/phpunit ^12.0`, `spatie/laravel-permission ^8.0`.
- Se retiraron `laravel/breeze` y `spatie/laravel-ignition` (ya no se usan: el
  andamiaje de Breeze quedó en el código y Laravel incluye sus propias páginas de error).
- `config/cache.php` incorpora `serializable_classes` y `config/session.php`
  incorpora `serialization = json` (endurecimiento contra ataques de deserialización).
  Este último invalida las sesiones activas una sola vez tras el despliegue.
- `config/logging.php` usa `max_files` (antes `days`) y `handler_with` (antes `with`).
- `config/mail.php` usa `scheme` en lugar de `encryption`; en `.env` se usa
  `MAIL_SCHEME` (por ejemplo `smtp` o `smtps`).
- `phpunit.xml` ejecuta las pruebas contra SQLite en memoria en lugar de la base
  de datos real (antes, con `RefreshDatabase`, las pruebas vaciaban la base de desarrollo).
- Se corrigió el modelo `App\Models\medidasPaciente` → `App\Models\MedidaPaciente`
  (PSR-4) y se agregó la columna `users.estatura` para calcular un IMC real.

## Idioma

La aplicación está en español (`APP_LOCALE=es`). Los textos del framework
(validación, autenticación, paginación) se publicaron en `lang/es` con
[laravel-lang](https://github.com/Laravel-Lang/common); para actualizarlos:

```bash
php artisan lang:update es
```

`laravel-lang/common` está en `require-dev`, así que no viaja a la imagen de
producción: los archivos de `lang/` ya quedan versionados en el repositorio.

## Visor de logs

`/log-viewer` (opcodesio/log-viewer) está restringido por rol en
`AppServiceProvider`: sólo los usuarios con rol `admin` pueden abrirlo. Sin esa
restricción el paquete deja el visor accesible a cualquiera.
