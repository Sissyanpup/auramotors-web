export type UserRole = "admin" | "seller" | "buyer";
export type SellerProfileStatus = "pending" | "approved" | "rejected";
export type SellerEntityType = "individu" | "perusahaan";
export type BuyerProfileStatus = "pending" | "approved" | "rejected";
export type BuyerIdType = "ktp" | "passport";
export type VehicleStatus = "draft" | "pending_review" | "approved" | "rejected" | "sold";

export type User = {
  id: number;
  name: string;
  email: string;
  bio: string | null;
  avatar_url: string | null;
  role: UserRole;
  ktp_number: string | null;
  ktp_name: string | null;
  has_completed_ktp: boolean;
  seller_profile_status?: SellerProfileStatus | null;
};

export type SellerProfile = {
  id: number;
  user?: User;
  entity_type: SellerEntityType;
  status: SellerProfileStatus;
  ktp_url: string;
  npwp_url: string | null;
  company_registration_url: string | null;
  articles_of_association_url: string | null;
  ubo_declaration_url: string | null;
  reviewed_by: string | null;
  reviewed_at: string | null;
  rejection_reason: string | null;
  bank_name: string | null;
  bank_account_number: string | null;
  bank_account_holder_name: string | null;
  created_at: string;
};

export type BuyerProfile = {
  id: number;
  user?: User;
  id_type: BuyerIdType;
  id_number: string;
  status: BuyerProfileStatus;
  id_document_url: string;
  address_proof_url: string;
  proof_of_funds_url: string;
  reviewed_by: string | null;
  reviewed_at: string | null;
  rejection_reason: string | null;
  created_at: string;
};

export type Vehicle = {
  id: number;
  brand: string;
  model: string;
  year: number;
  price: string;
  mileage: number;
  location: string;
  status: VehicleStatus;
  cover_photo_url: string | null;
  seller_name?: string;
};

export type VehiclePhoto = {
  id: number;
  url: string;
};

export type VehicleDocumentType =
  | "stnk"
  | "bpkb"
  | "service_history"
  | "inspection_report"
  | "certificate_of_authenticity";

export type VehicleDocument = {
  id: number;
  type: VehicleDocumentType;
  download_url: string;
};

export type VehiclePolicyType = "all_risk" | "tlo" | "agreed_value";
export type VinCheckStatus = "clean" | "warning" | "blocked";

export type VehicleInsurancePolicy = {
  id: number;
  policy_type: VehiclePolicyType;
  policy_type_label: string;
  insurer_name: string;
  policy_number: string;
  coverage_amount: string;
  agreed_value_amount: string | null;
  valid_from: string | null;
  valid_until: string | null;
  is_active: boolean;
  certificate_url: string;
  created_at: string;
};

export type VehicleVinCheck = {
  id: number;
  vin: string;
  status: VinCheckStatus;
  status_label: string;
  report: { flags: string[]; notes: string };
  checked_at: string;
};

export type PaymentOption = { label: string; percent: number };
export type InsuranceOption = { type: "none" | "tlo" | "all_risk"; label: string; premium: number };

export type VehicleDetail = {
  id: number;
  brand: string;
  model: string;
  year: number;
  vin: string | null;
  price: string;
  mileage: number;
  location: string;
  description: string | null;
  specs: Record<string, string> | null;
  payment_options?: PaymentOption[];
  insurance_options?: InsuranceOption[];
  status: VehicleStatus;
  reviewed_by: string | null;
  reviewed_at: string | null;
  rejection_reason: string | null;
  seller?: { id: number; name: string };
  photos?: VehiclePhoto[];
  documents?: VehicleDocument[];
  insurance_policies?: VehicleInsurancePolicy[];
  latest_vin_check?: VehicleVinCheck | null;
  created_at: string;
};

export type PaymentGateway = "mock" | "xendit";
export type PaymentStatus = "pending" | "paid" | "failed" | "expired" | "cancelled";
export type PaymentScheme = "down_payment" | "full";
export type InsuranceKind = "none" | "tlo" | "all_risk";
export type EscrowStatus =
  | "escrow_hold"
  | "serah_terima"
  | "payout_release"
  | "selesai"
  | "dispute"
  | "refunded";

export type TransactionDocumentType =
  | "commercial_invoice"
  | "deposit_receipt"
  | "bill_of_sale"
  | "sales_purchase_agreement"
  | "escrow_disbursement_note"
  | "tax_invoice";
export type TransactionDocumentFolder = "Financial" | "Legal";

export type TransactionDocument = {
  id: number;
  type: TransactionDocumentType;
  label: string;
  folder: TransactionDocumentFolder;
  download_url: string;
  generated_at: string;
};

export type TransactionStatusHistoryEntry = {
  id: number;
  from_status: string | null;
  to_status: string;
  actor: string | null;
  note: string | null;
  created_at: string;
};

export type PayoutMethod = "manual" | "xendit";
export type PayoutStatus = "pending" | "paid" | "failed";

export type TransactionPayout = {
  id: number;
  transaction_id: number;
  transaction?: { id: number; vehicle: string | null; seller: string | null; amount: string };
  method: PayoutMethod;
  status: PayoutStatus;
  commission_rate: string;
  commission_amount: string;
  payout_amount: string;
  reference: string | null;
  failure_reason: string | null;
  initiated_by: string | null;
  paid_at: string | null;
  created_at: string;
};

export type Transaction = {
  id: number;
  vehicle: Vehicle;
  buyer?: { id: number; name: string };
  seller?: { id: number; name: string };
  seller_bank_account?: { bank_name: string | null; bank_account_number: string | null; bank_account_holder_name: string | null };
  amount: string;
  vehicle_price: string | null;
  payment_scheme: PaymentScheme | null;
  dp_percent: string | null;
  insurance_type: InsuranceKind | null;
  insurance_premium: string | null;
  buyer_address: string | null;
  buyer_phone: string | null;
  buyer_notes: string | null;
  invoice_number: string | null;
  bank_transfer_bank: string | null;
  bank_transfer_account_number: string | null;
  bank_transfer_account_holder: string | null;
  payment_gateway: PaymentGateway;
  payment_status: PaymentStatus;
  gateway_reference: string | null;
  gateway_invoice_url: string | null;
  paid_at: string | null;
  expires_at: string | null;
  escrow_status: EscrowStatus | null;
  buyer_confirmed_at: string | null;
  seller_confirmed_at: string | null;
  dispute_reason: string | null;
  disputed_at: string | null;
  dispute_resolution_note: string | null;
  dispute_resolved_at: string | null;
  cancellation_reason: string | null;
  cancelled_at: string | null;
  payout_status: PayoutStatus | null;
  buyer_signed_at: string | null;
  seller_signed_at: string | null;
  status_history?: TransactionStatusHistoryEntry[];
  payouts?: TransactionPayout[];
  documents?: TransactionDocument[];
  shipment?: Shipment | null;
  created_at: string;
};

export type ShippingMode = "sea" | "air" | "land";
export type ShipmentStatus =
  | "draft"
  | "logistics_prep"
  | "in_transit"
  | "customs_clearance"
  | "delivered"
  | "delayed";
export type ShipmentDocumentType =
  | "export_declaration"
  | "bill_of_lading"
  | "air_waybill"
  | "import_declaration"
  | "customs_duty_receipt"
  | "certificate_of_conformity"
  | "proof_of_delivery"
  | "letter_of_acceptance";

export type ShipmentDocument = {
  id: number;
  type: ShipmentDocumentType;
  label: string;
  category: string;
  download_url: string;
  uploaded_by: string | null;
  created_at: string;
};

export type Shipment = {
  id: number;
  origin_country: string;
  destination_country: string;
  shipping_mode: ShippingMode;
  shipping_mode_label: string;
  carrier_name: string;
  tracking_number: string | null;
  estimated_arrival: string | null;
  actual_arrival: string | null;
  status: ShipmentStatus;
  status_label: string;
  cargo_insurance: {
    insurer_name: string | null;
    policy_number: string | null;
    coverage_amount: string | null;
    certificate_url: string | null;
    is_complete: boolean;
  };
  documents: ShipmentDocument[];
};

export type Paginated<T> = {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    total: number;
  };
};
