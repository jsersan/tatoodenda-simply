// Entorno de PRODUCCIÓN (ng build). Se activa por fileReplacements.
// apiUrl = ruta del backend PHP ya subido por FTP a txemaserrano.com.
// Los servicios le añaden /users, /categorias, /productos, /pedidos.
export const environment = {
  production: true,
  apiUrl: '/angular/ecommerce-backend/index.php',
  // Pega aquí tu OAuth Client ID de Google (termina en .apps.googleusercontent.com).
  // Vacío = el botón de Google no se muestra.
  googleClientId: ''
};
