"use client";

import { useEffect, useRef, useState, type PointerEvent as ReactPointerEvent } from "react";

type Props = {
  onSubmit: (dataUrl: string) => Promise<void> | void;
  onClose: () => void;
  isSubmitting?: boolean;
  title?: string;
  intro?: string;
  submitLabel?: string;
  errorMessage?: string | null;
};

/**
 * Canvas-based signature pad. User menggambar di canvas dengan pointer (mouse/touch/pen),
 * kemudian hasilnya diekspor sebagai PNG data URL untuk dikirim ke backend.
 */
export default function SignaturePad({
  onSubmit,
  onClose,
  isSubmitting = false,
  title = "Tanda Tangan Digital",
  intro = "Gambar tanda tangan Anda di kotak di bawah, lalu tekan Simpan.",
  submitLabel = "Simpan Tanda Tangan",
  errorMessage,
}: Props) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [isDrawing, setIsDrawing] = useState(false);
  const [hasStrokes, setHasStrokes] = useState(false);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ratio = window.devicePixelRatio || 1;
    // Fix backing store to CSS size for crisp strokes on HiDPI screens.
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.strokeStyle = "#1a1a1a";
    // White background — supaya PNG punya latar putih di PDF, bukan transparent hitam.
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, canvas.offsetWidth, canvas.offsetHeight);
  }, []);

  function getPos(e: ReactPointerEvent<HTMLCanvasElement>): { x: number; y: number } {
    const rect = e.currentTarget.getBoundingClientRect();
    return { x: e.clientX - rect.left, y: e.clientY - rect.top };
  }

  function handleDown(e: ReactPointerEvent<HTMLCanvasElement>) {
    e.currentTarget.setPointerCapture(e.pointerId);
    setIsDrawing(true);
    const ctx = canvasRef.current?.getContext("2d");
    if (!ctx) return;
    const { x, y } = getPos(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
  }

  function handleMove(e: ReactPointerEvent<HTMLCanvasElement>) {
    if (!isDrawing) return;
    const ctx = canvasRef.current?.getContext("2d");
    if (!ctx) return;
    const { x, y } = getPos(e);
    ctx.lineTo(x, y);
    ctx.stroke();
    setHasStrokes(true);
  }

  function handleUp(e: ReactPointerEvent<HTMLCanvasElement>) {
    if (isDrawing) e.currentTarget.releasePointerCapture(e.pointerId);
    setIsDrawing(false);
  }

  function handleClear() {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext("2d");
    if (!canvas || !ctx) return;
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, canvas.offsetWidth, canvas.offsetHeight);
    setHasStrokes(false);
  }

  async function handleSubmit() {
    if (!hasStrokes) return;
    const canvas = canvasRef.current;
    if (!canvas) return;
    const dataUrl = canvas.toDataURL("image/png");
    await onSubmit(dataUrl);
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div className="w-full max-w-lg rounded-lg border border-border bg-surface-container p-6 shadow-2xl">
        <h3 className="font-display text-lg text-on-surface">{title}</h3>
        <p className="mt-1 text-sm text-on-surface-muted">{intro}</p>

        <div className="mt-4 rounded-md border border-border bg-white">
          <canvas
            ref={canvasRef}
            onPointerDown={handleDown}
            onPointerMove={handleMove}
            onPointerUp={handleUp}
            onPointerCancel={handleUp}
            onPointerLeave={handleUp}
            className="block h-48 w-full touch-none rounded-md"
            style={{ cursor: "crosshair" }}
          />
        </div>

        <div className="mt-2 flex items-center justify-between">
          <p className="text-xs text-on-surface-muted">
            {hasStrokes ? "Siap disimpan. Klik Hapus untuk ulangi." : "Belum ada goresan."}
          </p>
          <button
            type="button"
            onClick={handleClear}
            disabled={!hasStrokes || isSubmitting}
            className="rounded-md border border-border px-3 py-1 text-xs text-on-surface-muted hover:border-error hover:text-error disabled:opacity-50"
          >
            Hapus
          </button>
        </div>

        {errorMessage && (
          <div className="mt-3 rounded-md border border-error/40 bg-error/5 p-2.5 text-xs text-error">
            {errorMessage}
          </div>
        )}

        <div className="mt-5 flex items-center justify-end gap-2 border-t border-border pt-4">
          <button
            type="button"
            onClick={onClose}
            disabled={isSubmitting}
            className="rounded-md border border-border px-4 py-2 text-sm text-on-surface hover:border-primary/40 disabled:opacity-50"
          >
            Batal
          </button>
          <button
            type="button"
            onClick={handleSubmit}
            disabled={!hasStrokes || isSubmitting}
            className="btn-gold rounded-md px-4 py-2 text-sm"
          >
            {isSubmitting ? "Menyimpan..." : submitLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
