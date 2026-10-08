export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export class ApiError extends Error {
  status: number;
  errors: Record<string, string[]> | null;

  constructor(status: number, message: string, errors: Record<string, string[]> | null = null) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

function readCookie(name: string): string | null {
  const match = document.cookie
    .split("; ")
    .find((row) => row.startsWith(`${name}=`));

  return match ? decodeURIComponent(match.split("=").slice(1).join("=")) : null;
}

function deleteCookie(name: string): void {
  // Hapus di host saat ini (frontend) sekaligus kemungkinan domain cross-port (host-only).
  // Browser cuma menerima penghapusan untuk pasangan domain/path persis yang dipakai saat set,
  // jadi kita coba beberapa kombinasi path "/" tanpa domain eksplisit — cukup untuk cookie
  // host-only yang di-set backend Sanctum.
  document.cookie = `${name}=; Max-Age=0; path=/`;
}

async function fetchCsrfCookie(): Promise<void> {
  // Hapus XSRF-TOKEN basi dulu supaya server pasti kirim ulang nilai yang segar.
  deleteCookie("XSRF-TOKEN");
  await fetch(`${API_URL}/sanctum/csrf-cookie`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  });
}

async function ensureCsrfCookie(): Promise<void> {
  if (readCookie("XSRF-TOKEN")) return;
  await fetchCsrfCookie();
}

type ApiFetchOptions = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: FormData | Record<string, unknown>;
};

function buildHeaders(options: ApiFetchOptions): { headers: Record<string, string>; body: BodyInit | undefined } {
  const headers: Record<string, string> = { Accept: "application/json" };
  const xsrfToken = readCookie("XSRF-TOKEN");
  if (xsrfToken) headers["X-XSRF-TOKEN"] = xsrfToken;

  let body: BodyInit | undefined;
  if (options.body instanceof FormData) {
    body = options.body;
  } else if (options.body) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(options.body);
  }

  return { headers, body };
}

export async function apiFetch<T>(path: string, options: ApiFetchOptions = {}): Promise<T> {
  const method = options.method ?? "GET";
  const isMutating = method !== "GET";

  if (isMutating) {
    await ensureCsrfCookie();
  }

  const sendRequest = async () => {
    const { headers, body } = buildHeaders(options);
    return fetch(`${API_URL}${path}`, { method, credentials: "include", headers, body });
  };

  let response = await sendRequest();

  // Token XSRF basi (misal server restart / session di-regenerate) menyebabkan 419.
  // Refetch cookie lalu retry sekali supaya alur login tidak kandas karena token lama.
  if (response.status === 419 && isMutating) {
    await fetchCsrfCookie();
    response = await sendRequest();
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const data = await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(
      response.status,
      data?.message ?? "Terjadi kesalahan pada server.",
      data?.errors ?? null
    );
  }

  return data as T;
}

export function toFormData(fields: Record<string, unknown>): FormData {
  const formData = new FormData();

  for (const [key, value] of Object.entries(fields)) {
    if (value === undefined || value === null) continue;

    if (Array.isArray(value)) {
      value.forEach((item) => {
        if (item instanceof File) {
          formData.append(`${key}[]`, item);
        } else {
          formData.append(`${key}[]`, String(item));
        }
      });
    } else if (value instanceof File) {
      formData.append(key, value);
    } else {
      formData.append(key, String(value));
    }
  }

  return formData;
}
