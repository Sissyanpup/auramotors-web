import VehicleForm from "@/components/VehicleForm";

export default function NewVehiclePage() {
  return (
    <div>
      <h1 className="text-xl font-semibold text-on-surface">Tambah Listing Kendaraan</h1>
      <p className="mt-1 text-sm text-on-surface-muted">
        Listing akan berstatus draft sampai kamu ajukan untuk review admin.
      </p>
      <div className="mt-6">
        <VehicleForm />
      </div>
    </div>
  );
}
