// login-popup.component.ts - VERSIÓN CORREGIDA
import { Component, ElementRef, EventEmitter, Input, NgZone, OnInit, Output, ViewChild, ViewEncapsulation } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { environment } from '../../../environments/environment';

// Librería de Google Identity Services (se carga dinámicamente)
declare const google: any;

@Component({
  selector: 'app-login-popup',
  templateUrl: './login-popup.component.html',
  styleUrls: ['./login-popup.component.scss'],
  encapsulation: ViewEncapsulation.None // CRÍTICO: Permite que los estilos se apliquen globalmente
})
export class LoginPopupComponent implements OnInit {
  @Input() isVisible: boolean = false;
  @Input() returnUrl: string = '/';              // ✅ AGREGADO: Input faltante
  @Input() fromCheckout: boolean = false;        // ✅ AGREGADO: Input faltante
  
  @Output() closePopup = new EventEmitter<void>();
  @Output() loginSuccess = new EventEmitter<any>(); // ✅ CORREGIDO: Cambiar a <any> para pasar el usuario
  @Output() switchToRegistro = new EventEmitter<void>();

  usuario: string = '';
  contrasena: string = '';
  isLoading: boolean = false;
  errorMessage: string = '';

  // --- Acceso con Google ---
  // Si googleClientId está vacío en environment, el botón no se muestra.
  readonly googleEnabled: boolean = !!environment.googleClientId;
  private googleInitialized = false;

  // Se ejecuta cada vez que el popup se abre y aparece el contenedor del botón.
  @ViewChild('googleBtn')
  set googleBtn(ref: ElementRef<HTMLElement> | undefined) {
    if (ref && this.googleEnabled) {
      this.renderGoogleButton(ref.nativeElement);
    }
  }

  constructor(
    private authService: AuthService,
    private router: Router,
    private ngZone: NgZone
  ) { }

  ngOnInit(): void {
    // Escuchar cambios en isVisible para manejar scroll del body
    this.handleBodyScroll();
  }

  ngOnChanges(): void {
    // Manejar scroll del body cuando cambia la visibilidad
    this.handleBodyScroll();
  }

  ngOnDestroy(): void {
    // Restaurar scroll al destruir el componente
    document.body.style.overflow = 'auto';
  }

  // Manejar el scroll del body
  private handleBodyScroll(): void {
    if (this.isVisible) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = 'auto';
    }
  }

  // Método para cerrar el popup
  cerrarPopup(): void {
    this.closePopup.emit();
    this.resetForm();
    document.body.style.overflow = 'auto'; // Restaurar scroll
  }

  // Método para cerrar el popup al hacer click en el overlay
  onOverlayClick(event: MouseEvent): void {
    if (event.target === event.currentTarget) {
      this.cerrarPopup();
    }
  }

  // Método para iniciar sesión
  iniciarSesion(): void {
    if (!this.usuario || !this.contrasena) {
      this.errorMessage = 'Por favor, completa todos los campos';
      return;
    }

    this.isLoading = true;
    this.errorMessage = '';

    this.authService.login(this.usuario, this.contrasena).subscribe({
      next: (response) => {
        this.isLoading = false;
        console.log('✅ Login exitoso en popup:', response);
        
        // ✅ IMPORTANTE: Emitir el usuario completo, no solo void
        this.loginSuccess.emit(response);
        this.cerrarPopup();
      },
      error: (error) => {
        this.isLoading = false;
        this.errorMessage = error.message || 'Usuario o contraseña incorrectos';
        console.error('❌ Error en login:', error);
      }
    });
  }

  // ---------- Acceso con Google (Google Identity Services) ----------

  private loadGoogleScript(): Promise<void> {
    return new Promise((resolve, reject) => {
      if (typeof google !== 'undefined' && google?.accounts?.id) {
        resolve();
        return;
      }
      const existing = document.getElementById('google-gsi-script');
      if (existing) {
        existing.addEventListener('load', () => resolve());
        existing.addEventListener('error', () => reject(new Error('No se pudo cargar Google')));
        return;
      }
      const script = document.createElement('script');
      script.id = 'google-gsi-script';
      script.src = 'https://accounts.google.com/gsi/client';
      script.async = true;
      script.defer = true;
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('No se pudo cargar Google'));
      document.head.appendChild(script);
    });
  }

  private async renderGoogleButton(host: HTMLElement): Promise<void> {
    try {
      await this.loadGoogleScript();

      if (!this.googleInitialized) {
        google.accounts.id.initialize({
          client_id: environment.googleClientId,
          callback: (resp: any) =>
            this.ngZone.run(() => this.onGoogleCredential(resp?.credential))
        });
        this.googleInitialized = true;
      }

      host.innerHTML = '';
      google.accounts.id.renderButton(host, {
        type: 'standard',
        theme: 'outline',
        size: 'large',
        text: 'continue_with',
        shape: 'rectangular',
        locale: 'es',
        width: Math.min(400, host.clientWidth || 360)
      });
    } catch (e) {
      console.error('⚠️ No se pudo mostrar el botón de Google:', e);
    }
  }

  // Google devuelve un ID token ("credential"): lo verifica el backend.
  private onGoogleCredential(credential: string | undefined): void {
    if (!credential) {
      return;
    }

    this.isLoading = true;
    this.errorMessage = '';

    this.authService.loginWithGoogle(credential).subscribe({
      next: (response) => {
        this.isLoading = false;
        this.loginSuccess.emit(response);
        this.cerrarPopup();
      },
      error: (error) => {
        this.isLoading = false;
        this.errorMessage = error.message || 'No se pudo iniciar sesión con Google';
      }
    });
  }

  // Método para navegar al registro
  irARegistro(): void {
    this.switchToRegistro.emit();
    this.resetForm();
  }

  // Método para navegar a la recuperación de contraseña
  irARecuperar(): void {
    this.cerrarPopup();                        // cierra el popup y restaura el scroll
    this.router.navigate(['/forgot-password']);
  }

  // Método para resetear el formulario
  private resetForm(): void {
    this.usuario = '';
    this.contrasena = '';
    this.errorMessage = '';
    this.isLoading = false;
  }

  // Método para manejar Enter en los campos
  onKeyPress(event: KeyboardEvent): void {
    if (event.key === 'Enter') {
      this.iniciarSesion();
    }
  }

  // Método para manejar la tecla Escape
  onKeyDown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      this.cerrarPopup();
    }
  }
}