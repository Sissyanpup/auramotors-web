import { redirect } from "next/navigation";

/**
 * URL lama halaman simulasi pembayaran. Invoice baru sudah mengarah ke
 * /bank-portal/{reference}; rute ini dipertahankan supaya link invoice lama
 * (yang tersimpan di transaksi sebelum Sprint 10) tetap berfungsi.
 */
export default async function LegacyMockPaymentPage(props: PageProps<"/checkout/mock/[reference]">) {
  const { reference } = await props.params;
  redirect(`/bank-portal/${reference}`);
}
