"use client";

import { useEffect, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import Image from "next/image";
import { ApiError, apiFetch, toFormData } from "@/lib/api";
import { useAuth } from "@/contexts/auth-context";
import ConfirmModal from "@/components/ConfirmModal";
import type { User } from "@/lib/types";

export default function ProfilPage() {
  const { user, isLoading, refresh } = useAuth();
  const router = useRouter();

  // — Info dasar
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [bio, setBio] = useState("");
  const [profileErrors, setProfileErrors] = useState<Record<string, string[]>>({});
  const [profileFormError, setProfileFormError] = useState<string | null>(null);
  const [profileSuccess, setProfileSuccess] = useState(false);
  const [isSavingProfile, setIsSavingProfile] = useState(false);

  // — Ganti password
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [newPasswordConfirmation, setNewPasswordConfirmation] = useState("");
  const [passwordErrors, setPasswordErrors] = useState<Record<string, string[]>>({});
  const [passwordFormError, setPasswordFormError] = useState<string | null>(null);
  const [passwordSuccess, setPasswordSuccess] = useState(false);
  const [isSavingPassword, setIsSavingPassword] = useState(false);

  // — Avatar
  const [avatarFile, setAvatarFile] = useState<File | null>(null);
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
  const [avatarErrors, setAvatarErrors] = useState<Record<string, string[]>>({});
  const [avatarFormError, setAvatarFormError] = useState<string | null>(null);
  const [isUploadingAvatar, setIsUploadingAvatar] = useState(false);

  // — Konfirmasi
  const [showProfileConfirm, setShowProfileConfirm] = useState(false);
  const [showPasswordConfirm, setShowPasswordConfirm] = useState(false);
  const [showAvatarConfirm, setShowAvatarConfirm] = useState(false);

  useEffect(() => {
    if (!isLoading && !user) {
      router.replace("/login");
    }
  }, [isLoading, user, router]);

  useEffect(() => {
    if (user) {
      setName(user.name);
      setEmail(user.email);
      setBio(user.bio ?? "");
    }
  }, [user]);

  function handleAvatarChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0] ?? null;
    setAvatarFile(file);
    if (file) {
      const url = URL.createObjectURL(file);
      setAvatarPreview(url);
    } else {
      setAvatarPreview(null);
    }
  }

  function handleProfileSubmit(e: FormEvent) {
    e.preventDefault();
    setShowProfileConfirm(true);
  }

  async function executeProfileUpdate() {
    setShowProfileConfirm(false);
    setProfileErrors({});
    setProfileFormError(null);
    setProfileSuccess(false);
    setIsSavingProfile(true);

    try {
      await apiFetch<{ data: User }>("/api/auth/profile", {
        method: "PUT",
        body: { name, email, bio: bio || null },
      });
      await refresh();
      setProfileSuccess(true);
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        setProfileErrors(error.errors);
      } else if (error instanceof ApiError) {
        setProfileFormError(error.message);
      }
    } finally {
      setIsSavingProfile(false);
    }
  }

  function handlePasswordSubmit(e: FormEvent) {
    e.preventDefault();
    setShowPasswordConfirm(true);
  }

  async function executePasswordUpdate() {
    setShowPasswordConfirm(false);
    setPasswordErrors({});
    setPasswordFormError(null);
    setPasswordSuccess(false);
    setIsSavingPassword(true);

    try {
      await apiFetch("/api/auth/profile/password", {
        method: "PUT",
        body: {
          current_password: currentPassword,
          password: newPassword,
          password_confirmation: newPasswordConfirmation,
        },
      });
      setPasswordSuccess(true);
      setCurrentPassword("");
      setNewPassword("");
      setNewPasswordConfirmation("");
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        setPasswordErrors(error.errors);
      } else if (error instanceof ApiError) {
        setPasswordFormError(error.message);
      }
    } finally {
      setIsSavingPassword(false);
    }
  }

  function handleAvatarSubmit(e: FormEvent) {
    e.preventDefault();
    if (!avatarFile) {
      setAvatarErrors({ avatar: ["Pilih foto terlebih dahulu."] });
      return;
    }
    setAvatarErrors({});
    setAvatarFormError(null);
    setShowAvatarConfirm(true);
  }

  async function executeAvatarUpdate() {
    if (!avatarFile) return;
    setShowAvatarConfirm(false);
    setIsUploadingAvatar(true);

    try {
      await apiFetch<{ data: User }>("/api/auth/profile/avatar", {
        method: "POST",
        body: toFormData({ avatar: avatarFile }),
      });
      await refresh();
      setAvatarFile(null);
      setAvatarPreview(null);
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        setAvatarErrors(error.errors);
      } else if (error instanceof ApiError) {
        setAvatarFormError(error.message);
      }
    } finally {
      setIsUploadingAvatar(false);
    }
  }

  if (isLoading || !user) {
    return <p className="p-8 text-sm text-on-surface-muted">Memuat...</p>;
  }

  const currentAvatar = avatarPreview ?? user.avatar_url;

  return (
    <div className="mx-auto max-w-2xl px-4 py-10">
      <h1 className="font-display text-2xl text-on-surface">Profil Saya</h1>
      <p className="mt-1 text-sm text-on-surface-muted">
        Kelola informasi akun, foto profil, dan keamanan.
      </p>

      {/* ── Avatar ── */}
      <section className="mt-8 rounded-lg border border-border bg-surface-container p-6">
        <h2 className="text-base font-semibold text-on-surface">Foto Profil</h2>

        <div className="mt-4 flex items-center gap-5">
          <div className="relative h-20 w-20 overflow-hidden rounded-full border border-border bg-surface-container-high">
            {currentAvatar ? (
              <Image
                src={currentAvatar}
                alt="Foto profil"
                fill
                className="object-cover"
                unoptimized
              />
            ) : (
              <span className="flex h-full w-full items-center justify-center text-2xl font-semibold text-on-surface-muted uppercase">
                {user.name.charAt(0)}
              </span>
            )}
          </div>

          <form onSubmit={handleAvatarSubmit} className="flex flex-1 flex-col gap-2">
            <input
              type="file"
              accept="image/jpeg,image/jpg,image/png,image/webp"
              onChange={handleAvatarChange}
              className="block w-full text-sm text-on-surface-muted file:mr-3 file:rounded-md file:border-0 file:bg-surface-container-high file:px-3 file:py-1 file:text-sm file:font-medium file:text-on-surface hover:file:bg-surface-container"
            />
            {avatarErrors.avatar && (
              <p className="text-xs text-error">{avatarErrors.avatar[0]}</p>
            )}
            {avatarFormError && <p className="text-xs text-error">{avatarFormError}</p>}
            <button
              type="submit"
              disabled={!avatarFile || isUploadingAvatar}
              className="self-start rounded-md bg-primary-container px-4 py-1.5 text-sm font-medium text-on-primary hover:bg-primary disabled:opacity-50"
            >
              {isUploadingAvatar ? "Mengunggah..." : "Simpan Foto"}
            </button>
          </form>
        </div>
        <p className="mt-3 text-xs text-on-surface-muted">
          Format: JPG, PNG, atau WebP. Maks 2 MB.
        </p>
      </section>

      {/* ── Info Dasar ── */}
      <section className="mt-6 rounded-lg border border-border bg-surface-container p-6">
        <h2 className="text-base font-semibold text-on-surface">Informasi Dasar</h2>

        <form onSubmit={handleProfileSubmit} className="mt-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-on-surface" htmlFor="profile-name">
              Nama Lengkap
            </label>
            <input
              id="profile-name"
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              className="input-field mt-1.5"
            />
            {profileErrors.name && (
              <p className="mt-1 text-xs text-error">{profileErrors.name[0]}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-on-surface" htmlFor="profile-email">
              Email
            </label>
            <input
              id="profile-email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="input-field mt-1.5"
            />
            {profileErrors.email && (
              <p className="mt-1 text-xs text-error">{profileErrors.email[0]}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-on-surface" htmlFor="profile-bio">
              Bio / Deskripsi{" "}
              <span className="font-normal text-on-surface-muted">(opsional)</span>
            </label>
            <textarea
              id="profile-bio"
              value={bio}
              onChange={(e) => setBio(e.target.value)}
              rows={3}
              maxLength={500}
              placeholder="Ceritakan sedikit tentang dirimu..."
              className="mt-1.5 block w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-on-surface placeholder:text-on-surface-muted focus:border-primary focus:outline-none resize-none"
            />
            <p className="mt-0.5 text-right text-xs text-on-surface-muted">{bio.length}/500</p>
            {profileErrors.bio && (
              <p className="text-xs text-error">{profileErrors.bio[0]}</p>
            )}
          </div>

          <div className="flex items-center gap-3">
            <button
              type="submit"
              disabled={isSavingProfile}
              className="rounded-md bg-primary-container px-4 py-2 text-sm font-medium text-on-primary hover:bg-primary disabled:opacity-50"
            >
              {isSavingProfile ? "Menyimpan..." : "Simpan Perubahan"}
            </button>
            {profileSuccess && (
              <p className="animate-fade-in text-sm text-success">Profil tersimpan.</p>
            )}
          </div>

          {profileFormError && (
            <p className="text-sm text-error" role="alert">
              {profileFormError}
            </p>
          )}
        </form>
      </section>

      {/* ── Ganti Password ── */}
      <section className="mt-6 rounded-lg border border-border bg-surface-container p-6">
        <h2 className="text-base font-semibold text-on-surface">Ganti Password</h2>
        <p className="mt-1 text-sm text-on-surface-muted">
          Gunakan password yang kuat dan unik.
        </p>

        <form onSubmit={handlePasswordSubmit} className="mt-4 space-y-4">
          <div>
            <label
              className="block text-sm font-medium text-on-surface"
              htmlFor="current-password"
            >
              Password Saat Ini
            </label>
            <input
              id="current-password"
              type="password"
              autoComplete="current-password"
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              required
              className="input-field mt-1.5"
            />
            {passwordErrors.current_password && (
              <p className="mt-1 text-xs text-error">{passwordErrors.current_password[0]}</p>
            )}
          </div>

          <div>
            <label
              className="block text-sm font-medium text-on-surface"
              htmlFor="new-password"
            >
              Password Baru
            </label>
            <input
              id="new-password"
              type="password"
              autoComplete="new-password"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              required
              className="input-field mt-1.5"
            />
            {passwordErrors.password && (
              <p className="mt-1 text-xs text-error">{passwordErrors.password[0]}</p>
            )}
          </div>

          <div>
            <label
              className="block text-sm font-medium text-on-surface"
              htmlFor="new-password-confirm"
            >
              Konfirmasi Password Baru
            </label>
            <input
              id="new-password-confirm"
              type="password"
              autoComplete="new-password"
              value={newPasswordConfirmation}
              onChange={(e) => setNewPasswordConfirmation(e.target.value)}
              required
              className="input-field mt-1.5"
            />
          </div>

          <div className="flex items-center gap-3">
            <button
              type="submit"
              disabled={isSavingPassword}
              className="rounded-md bg-primary-container px-4 py-2 text-sm font-medium text-on-primary hover:bg-primary disabled:opacity-50"
            >
              {isSavingPassword ? "Mengubah..." : "Ubah Password"}
            </button>
            {passwordSuccess && (
              <p className="animate-fade-in text-sm text-success">Password berhasil diubah.</p>
            )}
          </div>

          {passwordFormError && (
            <p className="text-sm text-error" role="alert">
              {passwordFormError}
            </p>
          )}
        </form>
      </section>

      {/* ── Info Role ── */}
      <section className="mt-6 rounded-lg border border-border bg-surface-container p-6">
        <h2 className="text-base font-semibold text-on-surface">Informasi Akun</h2>
        <dl className="mt-3 space-y-2 text-sm">
          <div className="flex gap-2">
            <dt className="w-28 text-on-surface-muted">Role</dt>
            <dd className="font-medium text-on-surface capitalize">{user.role}</dd>
          </div>
          {user.role === "seller" && user.seller_profile_status && (
            <div className="flex gap-2">
              <dt className="w-28 text-on-surface-muted">Status KYC</dt>
              <dd className="font-medium text-on-surface capitalize">{user.seller_profile_status}</dd>
            </div>
          )}
        </dl>
      </section>

      {showProfileConfirm && (
        <ConfirmModal
          title="Simpan perubahan profil?"
          message={
            <span>
              Nama dan/atau email akan diperbarui. Email baru:{" "}
              <strong className="text-on-surface">{email}</strong>
            </span>
          }
          confirmLabel="Ya, Simpan"
          isSubmitting={isSavingProfile}
          onConfirm={executeProfileUpdate}
          onCancel={() => setShowProfileConfirm(false)}
        />
      )}

      {showPasswordConfirm && (
        <ConfirmModal
          title="Ubah password?"
          message="Password lama akan diganti. Pastikan kamu ingat password barumu."
          confirmLabel="Ya, Ubah Password"
          isSubmitting={isSavingPassword}
          onConfirm={executePasswordUpdate}
          onCancel={() => setShowPasswordConfirm(false)}
        />
      )}

      {showAvatarConfirm && (
        <ConfirmModal
          title="Ganti foto profil?"
          message="Foto profil saat ini akan diganti dengan foto yang baru kamu pilih."
          confirmLabel="Ya, Ganti Foto"
          isSubmitting={isUploadingAvatar}
          onConfirm={executeAvatarUpdate}
          onCancel={() => setShowAvatarConfirm(false)}
        />
      )}
    </div>
  );
}
