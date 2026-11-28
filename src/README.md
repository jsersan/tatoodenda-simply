# TatooDenda · Frontend (`src/`)

Código fuente del frontend Angular de **TatooDenda**, una tienda online de piercings y dilataciones. Se despliega en `https://txemaserrano.com/angular/ecommerce/` y consume el backend PHP de la carpeta `backend-php/` de este mismo repositorio.

## Estructura

```
src/
├── app/
│   ├── components/             Componentes de la interfaz (incluye login-popup)
│   ├── guards/                 Guards de rutas
│   ├── helpers/                Utilidades
│   ├── interceptors/           Interceptores HTTP
│   ├── models/                 Interfaces y modelos (User, ...)
│   ├── pipes/                  Pipes personalizados
│   ├── services/               Servicios que hablan con el backend
│   ├── app-routing.module.ts   Rutas
│   ├── app.module.ts           Módulo raíz
│   └── app.component.*         Componente raíz
├── assets/                     Imágenes y recursos estáticos
├── environments/               Configuración por entorno
└── script/                     Scripts auxiliares
```

### Servicios (`src/app/services/`)

| Servicio | Responsabilidad |
|---|---|
| `auth.service.ts` | Login, registro, login con Google, recuperación de contraseña, sesión del usuario |
| `login-popup.service.ts` | Abrir y cerrar el popup de login (también desde el checkout) |
| `product.service.ts` | Productos |
| `category.service.ts` | Categorías |
| `cart.service.ts` | Carrito |
| `order.service.ts` | Pedidos |
| `image.service.ts` | Imágenes |
| `pdf.service.ts` | Generación de PDF |

## Configuración por entorno

Los dos archivos de `src/environments/` definen la URL del backend y el Client ID de Google:

| Archivo | Uso | `apiUrl` |
|---|---|---|
| `environment.ts` | `ng serve` (desarrollo) | `https://txemaserrano.com/angular/ecommerce-backend/index.php` (absoluta, porque `ng serve` corre en `localhost:4200`) |
| `environment.prod.ts` | `ng build` (producción, vía `fileReplacements`) | `/angular/ecommerce-backend/index.php` (relativa) |

```ts
export const environment = {
  production: false,
  apiUrl: '...',
  googleClientId: 'XXXXXXXX.apps.googleusercontent.com'
};
```

> ⚠️ **El entorno de desarrollo usa el mismo backend y la misma base de datos que producción.** Cualquier pedido, registro o cambio que hagas con `ng serve` afecta a datos reales.

`googleClientId` debe ser idéntico en los dos archivos y coincidir con el `google_client_id` de `backend-php/config.php`. Si se deja vacío, el botón de Google no se muestra.

## Autenticación

La sesión se guarda en `localStorage` (clave `currentUser`) y el usuario incluye el `token` JWT que devuelve el backend.

### Login con usuario y contraseña

`POST /users/login` con `{ username, password }`. El acceso se hace por **nombre de usuario**, no por email.

### Login con Google

1. El popup de login (`login-popup.component`) carga Google Identity Services y dibuja el botón oficial usando `googleClientId`.
2. Al iniciar sesión, Google devuelve un ID token (`credential`).
3. `AuthService.loginWithGoogle()` lo envía a `POST /users/google`.
4. El backend verifica el token con Google. Si el email ya existe en la tienda, el usuario entra en esa cuenta. Si no, se crea uno con un `username` generado a partir del email.
5. La respuesta es la misma que la del login normal (usuario + `token`), así que el resto de la aplicación no cambia.

### Recuperación de contraseña

- `POST /users/forgot-password` con `{ email }`.
- `GET /users/verify-reset-token/{token}`.
- `POST /users/reset-password` con `{ token, newPassword }`.

El enlace del email apunta a `/reset-password?token=...` sobre la `app_url` del backend.

### Administrador

`AuthService.isAdmin()` comprueba en el cliente que el `username` sea `admin`. Sirve solo para mostrar u ocultar la interfaz: la autorización real la hace el backend con el rol incluido en el JWT.

## Desarrollo

```bash
npm install
ng serve          # http://localhost:4200
```

El backend solo acepta peticiones de los orígenes de `cors_origins` (en `backend-php/config.php` y `config.local.php`). Para desarrollo local ya está permitido `http://localhost:4200`.

## Compilar y desplegar

```bash
ng build          # genera la carpeta dist/
```

1. Comprueba que el `baseHref` (en `angular.json` o con `--base-href`) coincide con la ruta de despliegue.
2. Sube el contenido de `dist/` por FTP a `/angular/ecommerce/`, incluido el `.htaccess`.
3. Si has cambiado el backend, sube también `backend-php/` a `/angular/ecommerce-backend/`. Los secretos de producción viven en `config.local.php`, que no está en Git.

## Seguridad

- No subas contraseñas, `jwt_secret` ni credenciales SMTP al repositorio. Van solo en `backend-php/config.local.php`.
- El `googleClientId` es público y puede estar en los `environment.ts`. El secreto del cliente de Google no se usa en este flujo y no debe subirse nunca.
