"use client";

import { createContext, useCallback, useContext, useEffect, useState } from "react";
import { ApiError, apiFetch } from "@/lib/api";
import type { User } from "@/lib/types";

type RegisterPayload = {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role: "seller" | "buyer";
};

type AuthContextValue = {
  user: User | null;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (payload: RegisterPayload) => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const refresh = useCallback(async () => {
    try {
      const { data } = await apiFetch<{ data: User }>("/api/auth/me");
      setUser(data);
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        setUser(null);
      }
    }
  }, []);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- initial session hydration on mount
    refresh().finally(() => setIsLoading(false));
  }, [refresh]);

  const login = useCallback(async (email: string, password: string) => {
    const { data } = await apiFetch<{ data: User }>("/api/auth/login", {
      method: "POST",
      body: { email, password },
    });
    setUser(data);
  }, []);

  const register = useCallback(async (payload: RegisterPayload) => {
    const { data } = await apiFetch<{ data: User }>("/api/auth/register", {
      method: "POST",
      body: payload,
    });
    setUser(data);
  }, []);

  const logout = useCallback(async () => {
    await apiFetch("/api/auth/logout", { method: "POST" });
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider value={{ user, isLoading, login, register, logout, refresh }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}
