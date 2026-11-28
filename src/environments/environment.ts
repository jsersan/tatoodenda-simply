// Entorno de DESARROLLO (ng serve).
// Usa el mismo backend PHP de Arsys que producción, y por tanto la misma
// base de datos qamn130. URL absoluta porque ng serve corre en localhost:4200.
export const environment = {
  production: false,
  apiUrl: 'https://txemaserrano.com/angular/ecommerce-backend/index.php',
  googleClientId: '258914466032-s0c4khhlnsj5uub7qijir9vig2t5rgbd.apps.googleusercontent.com'
};