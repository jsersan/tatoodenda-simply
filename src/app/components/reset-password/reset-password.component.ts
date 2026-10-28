// src/app/components/reset-password/reset-password.component.ts
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-reset-password',
  templateUrl: './reset-password.component.html',
  styleUrls: ['./reset-password.component.scss'],
  standalone: false
})
export class ResetPasswordComponent implements OnInit {
  token = '';
  tokenValido = false;
  comprobando = true;

  password = '';
  password2 = '';
  loading = false;
  message = '';
  error = '';

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private authService: AuthService
  ) {}

  ngOnInit(): void {
    this.token = this.route.snapshot.queryParamMap.get('token') || '';
    if (!this.token) {
      this.comprobando = false;
      this.tokenValido = false;
      return;
    }
    this.authService.verifyResetToken(this.token).subscribe({
      next: (res) => {
        this.comprobando = false;
        this.tokenValido = !!res?.valid;
      },
      error: () => {
        this.comprobando = false;
        this.tokenValido = false;
      }
    });
  }

  guardar(): void {
    this.error = '';
    if (this.password.length < 4) {
      this.error = 'La contraseña debe tener al menos 4 caracteres.';
      return;
    }
    if (this.password !== this.password2) {
      this.error = 'Las contraseñas no coinciden.';
      return;
    }
    this.loading = true;
    this.authService.resetPassword(this.token, this.password).subscribe({
      next: (res) => {
        this.loading = false;
        this.message = res?.message || 'Contraseña actualizada. Ya puedes iniciar sesión.';
        setTimeout(() => this.router.navigate(['/']), 2500);
      },
      error: (err) => {
        this.loading = false;
        this.error = err?.error?.message || 'No se pudo restablecer la contraseña.';
      }
    });
  }
}
