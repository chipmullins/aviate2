import { Component, computed, inject, signal } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ImageCroppedEvent, ImageCropperComponent } from 'ngx-image-cropper';
import { Api } from '../core/api.service';
import { AuthService, errorMessage, fieldErrors } from '../core/auth.service';
import { ToastService } from '../core/toast.service';
import { Avatar } from '../shared/avatar';

const matchPasswords = (g: AbstractControl): ValidationErrors | null =>
  g.get('password')?.value === g.get('password_confirmation')?.value ? null : { mismatch: true };

@Component({
  selector: 'app-settings-page',
  imports: [ReactiveFormsModule, Avatar, ImageCropperComponent, RouterLink],
  templateUrl: './settings.page.html',
  styles: `
    .photo { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
    .photo-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .cropper { max-width: 420px; margin: 16px 0; }
    .danger { border-color: #f3c4c4; }
    .danger h2 { color: var(--danger); }
    .actions { display: flex; gap: 8px; }
    .lock { border: 0; margin: 0; padding: 0; min-width: 0; display: flex; flex-direction: column; gap: 16px; }
    .lock:disabled { opacity: 0.6; }
    .lock:disabled label.btn { pointer-events: none; }
  `,
})
export class SettingsPage {
  private api = inject(Api);
  private toast = inject(ToastService);
  protected auth = inject(AuthService);
  private fb = inject(FormBuilder).nonNullable;

  /** Shared demo accounts can't be changed, so one visitor can't lock out the next. */
  protected locked = computed(() => !!this.auth.user()?.is_demo);

  // ---- Details
  protected details = this.fb.group({
    first_name: [this.auth.user()?.first_name ?? '', [Validators.required, Validators.maxLength(50)]],
    last_name: [this.auth.user()?.last_name ?? '', [Validators.required, Validators.maxLength(50)]],
    email: [this.auth.user()?.email ?? '', [Validators.required, Validators.email]],
  });
  protected detailsErrors = signal<Record<string, string>>({});
  protected detailsBusy = signal(false);

  // ---- Password
  protected password = this.fb.group(
    {
      current_password: ['', Validators.required],
      password: ['', [Validators.required, Validators.minLength(8)]],
      password_confirmation: ['', Validators.required],
    },
    { validators: matchPasswords },
  );
  protected passwordErrors = signal<Record<string, string>>({});
  protected passwordBusy = signal(false);

  // ---- Photo
  protected pickedFile = signal<File | null>(null);
  protected cropped = signal<Blob | null>(null);
  protected photoBusy = signal(false);

  // ---- Close account
  protected closing = signal(false);
  protected closeForm = this.fb.group({ password: ['', Validators.required] });
  protected closeError = signal<string | null>(null);

  protected saveDetails(): void {
    if (this.details.invalid) return;
    this.detailsBusy.set(true);
    this.detailsErrors.set({});
    this.api.updateProfile(this.details.getRawValue()).subscribe({
      next: ({ data }) => {
        this.auth.setUser(data);
        this.detailsBusy.set(false);
        this.toast.show('Details saved');
      },
      error: (err) => {
        this.detailsBusy.set(false);
        this.detailsErrors.set(fieldErrors(err));
        if (!Object.keys(fieldErrors(err)).length) this.toast.error(errorMessage(err));
      },
    });
  }

  protected savePassword(): void {
    if (this.password.invalid) return;
    this.passwordBusy.set(true);
    this.passwordErrors.set({});
    this.api.updatePassword(this.password.getRawValue()).subscribe({
      next: () => {
        this.passwordBusy.set(false);
        this.password.reset();
        this.toast.show('Password changed');
      },
      error: (err) => {
        this.passwordBusy.set(false);
        this.passwordErrors.set(fieldErrors(err));
        if (!Object.keys(fieldErrors(err)).length) this.toast.error(errorMessage(err));
      },
    });
  }

  protected pickPhoto(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return this.toast.error('Photos must be JPG, PNG or WebP.');
    if (file.size > 5 * 1024 * 1024) return this.toast.error('Photos must be 5 MB or smaller.');
    this.cropped.set(null);
    this.pickedFile.set(file);
  }

  protected onCropped(e: ImageCroppedEvent): void {
    this.cropped.set(e.blob ?? null);
  }

  protected cancelPhoto(): void {
    this.pickedFile.set(null);
    this.cropped.set(null);
  }

  protected savePhoto(): void {
    const blob = this.cropped();
    if (!blob) return;
    this.photoBusy.set(true);
    this.api.uploadAvatar(blob).subscribe({
      next: ({ data }) => {
        this.auth.setUser(data);
        this.photoBusy.set(false);
        this.cancelPhoto();
        this.toast.show('Profile photo updated');
      },
      error: (err) => {
        this.photoBusy.set(false);
        this.toast.error(errorMessage(err, 'That photo couldn’t be uploaded.'));
      },
    });
  }

  protected removePhoto(): void {
    this.api.removeAvatar().subscribe({
      next: ({ data }) => this.auth.setUser(data),
      error: (err) => this.toast.error(errorMessage(err)),
    });
  }

  protected closeAccount(): void {
    if (this.closeForm.invalid) return;
    this.closeError.set(null);
    this.api.closeAccount(this.closeForm.getRawValue().password).subscribe({
      next: () => {
        this.toast.show('Your account is closed. Log in any time to reopen it.');
        this.auth.signedOut();
      },
      error: (err) => this.closeError.set(errorMessage(err)),
    });
  }
}
