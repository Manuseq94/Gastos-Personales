# Aplicación de Gastos Personales

Aplicación web fullstack para registrar y administrar gastos personales, ingresos y tarjetas.

## ¿Qué hace la app?

- Permite registrar e iniciar sesión.
- Guarda sesión con token Bearer.
- Permite cargar, editar, listar y eliminar gastos.
- Permite cargar, editar, listar y eliminar ingresos.
- Permite cargar, editar, listar y eliminar tarjetas.
- Si un gasto es con débito o crédito, podés asociarlo a una tarjeta.
- Si un gasto es con crédito, se indica la cantidad de cuotas y el servidor calcula el valor de cuota.
- Calcula métricas mensuales: gastos, ingresos, economía y balance por día de cierre.
- El día de cierre se configura dentro de la app (pantalla de Inicio).

## Estructura de carpetas

```text
gastos-personales-app/
├── README.md
├── backend/
│   ├── .env.example
│   ├── .htaccess
│   ├── database.sql
│   ├── config/
│   │   └── database.php
│   ├── utils/
│   │   ├── response.php
│   │   ├── auth.php
│   │   ├── validaciones.php
│   │   └── email.php
│   └── api/
│       ├── register.php
│       ├── login.php
│       ├── gastos.php
│       ├── ingresos.php
│       ├── tarjetas.php
│       ├── metricas.php
│       └── configuracion.php
└── frontend/
    ├── index.html
    ├── dashboard.html
    ├── gastos.html
    ├── ingresos.html
    ├── tarjetas.html
    ├── css/
    │   └── style.css
    └── js/
        ├── api.js
        ├── auth.js
        ├── gastos.js
        ├── ingresos.js
        ├── tarjetas.js
        └── metricas.js
```

## Instalación y puesta en marcha (paso a paso)

1. **Creá la base de datos y tablas**
   Ejecutá este script SQL:
   ```sql
   SOURCE /ruta/a/gastos-personales-app/backend/database.sql;
   ```

2. **Configurá variables de entorno**
   - Copiá `backend/.env.example` como `backend/.env`.
   - Completá tus credenciales de MySQL.
   - Si querés usar Brevo, cargá `BREVO_API_KEY`.

3. **Publicá el proyecto en Apache + PHP**
   - Dejá la carpeta `gastos-personales-app` visible desde tu servidor.
   - La API va a responder en:
     `http://localhost/gastos-personales-app/backend/api`

4. **Revisá URL base en frontend**
   - En `frontend/js/api.js`, la constante debe ser:
     `http://localhost/gastos-personales-app/backend/api`

5. **Abrí la aplicación**
   - Entrá a `frontend/index.html` desde tu servidor web.

## Variables de entorno (`backend/.env.example`)

| Variable | Para qué sirve |
|---|---|
| `DB_HOST` | Host de MySQL (ej: `127.0.0.1`, `localhost`, contenedor). |
| `DB_PORT` | Puerto de MySQL. |
| `DB_NAME` | Nombre de la base de datos. |
| `DB_USER` | Usuario de MySQL. |
| `DB_PASS` | Contraseña de MySQL. |
| `CORS_ALLOW_ORIGIN` | Origen permitido para CORS (en desarrollo suele usarse `*`). |
| `TOKEN_EXPIRATION_HOURS` | Horas de validez del token de sesión. |
| `BREVO_API_KEY` | API key de Brevo (opcional). Si está vacía, no se envía email. |
| `BREVO_FROM_EMAIL` | Email del remitente para Brevo. |
| `BREVO_FROM_NAME` | Nombre del remitente para Brevo. |

## Códigos HTTP usados

- `200` OK
- `201` Creado
- `204` Preflight `OPTIONS` sin contenido
- `400` Solicitud inválida
- `401` No autorizado
- `404` Recurso no encontrado
- `405` Método no permitido
- `409` Conflicto (ejemplo: email ya registrado)
- `422` Error de validación
- `500` Error interno del servidor

## Endpoints de la API

Base URL: `http://localhost/gastos-personales-app/backend/api`

---

### `POST /register.php`

- **Authorization**: no requiere token.
- **Body JSON**:

```json
{
  "nombre": "Juan Pérez",
  "email": "juan@mail.com",
  "password": "secreto123"
}
```

- El usuario nuevo arranca con `dia_cierre_balance = 1`; se cambia luego con `PUT /configuracion.php`.
- **Respuestas**:
  - `201` usuario creado + token
  - `409` email ya registrado
  - `422` validación
  - `405`, `500`

---

### `POST /login.php`

- **Authorization**: no requiere token.
- **Body JSON**:

```json
{
  "email": "juan@mail.com",
  "password": "secreto123"
}
```

- **Respuestas**:
  - `200` login correcto + token
  - `401` credenciales inválidas
  - `422`, `405`, `500`

---

### `GET /gastos.php`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: sin parámetros.
- **Respuestas**:
  - `200` lista de gastos
  - `401`, `405`, `500`

### `GET /gastos.php?id={id}`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: `id` por query string.
- **Respuestas**:
  - `200` gasto puntual
  - `404` no encontrado
  - `401`, `405`, `500`

### `POST /gastos.php`

- **Authorization**: `Bearer <token>`.
- **Body JSON**:

```json
{
  "descripcion": "Supermercado",
  "monto": 25000,
  "fecha": "2026-10-19",
  "categoria": "Alimentos",
  "metodo_pago": "credito",
  "tarjeta_id": 2,
  "cantidad_cuotas": 3
}
```

- `metodo_pago`: `efectivo | debito | credito | billetera_virtual`.
- Si el método es `debito` o `credito`, `tarjeta_id` es obligatoria y debe coincidir el tipo.
- `efectivo`, `billetera_virtual` y `debito`: solo monto (se guarda 1 cuota del monto total).
- `credito`: `cantidad_cuotas` es obligatoria (entero de 1 a 60).
- `valor_cuota` y `valor_total` los calcula el servidor (`monto / cantidad_cuotas`, sin interés); si se envían, se ignoran.
- **Respuestas**:
  - `201` creado
  - `422` validación
  - `401`, `405`, `500`

### `PUT /gastos.php?id={id}`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: `id` en query string.
- **Body JSON**: igual a POST.
- **Respuestas**:
  - `200` actualizado
  - `404` no encontrado
  - `422`, `401`, `405`, `500`

### `DELETE /gastos.php?id={id}`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: `id` en query string.
- **Respuestas**:
  - `200` eliminado
  - `404`, `422`, `401`, `405`, `500`

---

### `GET /ingresos.php`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: sin parámetros.
- **Respuestas**:
  - `200` lista de ingresos
  - `401`, `405`, `500`

### `GET /ingresos.php?id={id}`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: `id` en query string.
- **Respuestas**:
  - `200` ingreso puntual
  - `404`, `401`, `405`, `500`

### `POST /ingresos.php`

- **Authorization**: `Bearer <token>`.
- **Body JSON**:

```json
{
  "descripcion": "Sueldo",
  "monto": 850000,
  "fecha": "2026-10-01",
  "es_fijo": true
}
```

- **Respuestas**:
  - `201` creado
  - `422` validación
  - `401`, `405`, `500`

### `PUT /ingresos.php?id={id}`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: `id` en query string.
- **Body JSON**: igual a POST.
- **Respuestas**:
  - `200` actualizado
  - `404` no encontrado o sin cambios
  - `422`, `401`, `405`, `500`

### `DELETE /ingresos.php?id={id}`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: `id` en query string.
- **Respuestas**:
  - `200` eliminado
  - `404`, `422`, `401`, `405`, `500`

---

### `GET /tarjetas.php`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: sin parámetros.
- **Respuestas**:
  - `200` lista de tarjetas
  - `401`, `405`, `500`

### `GET /tarjetas.php?id={id}`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: `id` en query string.
- **Respuestas**:
  - `200` tarjeta puntual
  - `404`, `401`, `405`, `500`

### `GET /tarjetas.php?con_gastos=1`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: `con_gastos=1`.
- **Respuestas**:
  - `200` tarjetas con `gastos_asociados`
  - `401`, `405`, `500`

### `GET /tarjetas.php?id={id}&con_gastos=1`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: `id` y `con_gastos=1`.
- **Respuestas**:
  - `200` tarjeta puntual con `gastos_asociados`
  - `404`, `401`, `405`, `500`

### `POST /tarjetas.php`

- **Authorization**: `Bearer <token>`.
- **Body JSON**:

```json
{
  "nombre": "Visa Galicia",
  "tipo": "credito",
  "banco": "Galicia",
  "ultimos_4": "1234"
}
```

- `tipo`: `debito | credito`.
- **Respuestas**:
  - `201` creada
  - `422` validación
  - `401`, `405`, `500`

### `PUT /tarjetas.php?id={id}`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: `id` en query string.
- **Body JSON**: igual a POST.
- **Respuestas**:
  - `200` actualizada
  - `404` no encontrada o sin cambios
  - `422`, `401`, `405`, `500`

### `DELETE /tarjetas.php?id={id}`

- **Authorization**: `Bearer <token>`.
- **Parámetros**: `id` en query string.
- **Respuestas**:
  - `200` eliminada
  - `404`, `422`, `401`, `405`, `500`

---

### `GET /metricas.php`

- **Authorization**: `Bearer <token>`.
- **Parámetros opcionales**:
  - `mes=YYYY-MM`
  - `dia_cierre=1..28`
- Si no se ingresa `mes`, usa el mes actual.
- Si no se ingresa `dia_cierre`, usa el guardado del usuario.
- El período de balance va del día siguiente al cierre anterior hasta el día de cierre (inclusive).
- **Respuestas**:
  - `200` con totales, categorías, economía y balance por cierre
  - `422`, `401`, `405`, `500`

---

### `GET /configuracion.php`

- **Authorization**: `Bearer <token>`.
- **Respuestas**:
  - `200` con `dia_cierre_balance`
  - `401`, `405`, `500`

### `PUT /configuracion.php`

- **Authorization**: `Bearer <token>`.
- **Body JSON**:

```json
{
  "dia_cierre_balance": 12
}
```

- **Respuestas**:
  - `200` día de cierre actualizado
  - `422`, `401`, `405`, `500`

## Problemas comunes (y cómo resolverlos)

### 1) Al importar `database.sql` phpMyAdmin dice que las tablas ya existen

Es normal. El script usa `CREATE TABLE IF NOT EXISTS`, así que se puede importar varias veces sin romper nada.
No es un error del TP.

### 2) Aparece "La respuesta del servidor no es JSON válido"

Significa que el frontend esperaba JSON y el servidor devolvió otra cosa (muchas veces HTML de error o ruta mal armada).
Fijate estos puntos:

- Apache y MySQL tienen que estar encendidos.
- La carpeta debe estar en `htdocs` con este nombre exacto: `gastos-personales-app`.
- En la barra del navegador no tiene que decir `file://`.
- Tiene que existir `backend/.env` (copialo desde `.env.example`, aunque sea con valores por defecto).

### 3) Cómo probar el backend a mano

- `http://localhost/gastos-personales-app/backend/api/registro` **no existe**.
- Probá en cambio:
  - `http://localhost/gastos-personales-app/backend/api/login.php` → debería devolver JSON con "Método no permitido" si lo abrís por GET.
  - `http://localhost/gastos-personales-app/backend/api/gastos.php` → debería devolver JSON pidiendo token.

Si en vez de JSON ves una página HTML de error o un 404 de Apache, el problema es de ruta/Apache, no del código de la API.

### 4) ¿Qué significa error 401?

Que el token es inválido o venció. En ese caso, simplemente volvé a iniciar sesión.

## Notas finales

- El backend usa **PHP + PDO + sentencias preparadas**.
- El preflight `OPTIONS` responde con `204`.
- Si `BREVO_API_KEY` está vacía, la app sigue funcionando sin enviar emails.
