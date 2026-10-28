// src/app/components/forgot-password/forgot-password.component.ts
import { Component } from '@angular/core';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-forgot-password',
  templateUrl: './forgot-password.component.html',
  styleUrls: ['./forgot-password.component.scss'],
  standalone: false
})
export class ForgotPasswordComponent {
  email = '';
  loading = false;
  done = false;
  message = '';
  // Solo en desarrollo: el backend devuelve aquí el enlace de reset para
  // poder probar sin correo. En producción llega vacío.
  devResetLink = '';

  constructor(private authService: AuthService) {}

  enviar(): void {
    if (!this.email) return;
    this.loading = true;
    this.message = '';
    this.devResetLink = '';
    this.authService.forgotPassword(this.email).subscribe({
      next: (res) => {
        this.loading = false;
        this.done = true;
        // El backend responde siempre lo mismo, exista o no el email.
        this.message = res?.message || 'Si el email existe, recibirás un enlace de recuperación.';
        // Enlace de desarrollo (solo presente si el backend está en modo dev).
        this.devResetLink = res?.devResetLink || '';
      },
      error: () => {
        this.loading = false;
        this.done = true;
        this.message = 'Si el email existe, recibirás un enlace de recuperación.';
      }
    });
  }
}
