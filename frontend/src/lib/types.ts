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
  /** Promo potongan harga terbaik yang sedang berjalan (harga coret). */
  promo?: CatalogPromo | null;
  mileage: number;
  location: string;
  status: VehicleStatus;
  cover_photo_url: string | null;
  seller_name?: string;
  /** Jejak verifikasi — hanya dikirim untuk admin. */
  reviewed_by?: string | null;
  reviewed_at?: string | null;
  rejection_reason?: string | null;
  created_at?: string;
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
  /** Promo potongan harga terbaik yang sedang berjalan (harga coret). */
  promo?: CatalogPromo | null;
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
export type PaymentScheme = "down_payment" | "full" | "credit";
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
  | "tax_invoice"
  | "price_breakdown"
  | "insurance_application"
  | "insurance_policy"
  | "bank_payment_advice"
  | "escrow_fund_confirmation"
  | "seller_ownership_statement"
  | "ownership_transfer_poa"
  | "handover_minutes"
  | "sale_receipt";
export type TransactionDocumentFolder = "Financial" | "Legal" | "Insurance" | "Bank";

export type SignerRole = "buyer" | "seller";

export type HandoverChecklistItem = {
  key: string;
  label: string;
  is_met: boolean;
};

export type SigningChecklistItem = {
  type: TransactionDocumentType;
  label: string;
  folder: TransactionDocumentFolder;
  download_url: string;
  signers: { role: SignerRole; name: string | null; signed_at: string | null }[];
  is_complete: boolean;
};

/** Satu baris rincian harga: label + rumus dalam bahasa manusia + jumlah. */
export type BreakdownLine = { label: string; basis?: string; amount: number; code?: string; payee?: string };

export type InsuranceAddonOption = {
  code: string;
  label: string;
  description: string;
  basis: string;
  premium: number;
};

/** Snapshot dari App\Support\PriceBreakdownCalculator (backend). */
export type PriceBreakdown = {
  version: number;
  vehicle_price: number;
  payment: { scheme: PaymentScheme; dp_percent: number; dp_amount: number; remaining_amount: number };
  price_composition: { lines: BreakdownLine[]; total: number; note: string };
  insurance: {
    type: InsuranceKind;
    label: string;
    insurer: string | null;
    sum_insured: number;
    addons: string[];
    lines: BreakdownLine[];
    total: number;
    deductible_per_claim: number | null;
    theft_settlement_percent: number | null;
    total_loss_threshold_percent: number | null;
  };
  due_now: { lines: BreakdownLine[]; total: number };
  /** in_escrow = true: biaya balik nama ditagih bersama DP (snapshot versi 2). */
  registration: { njkb: number; njkb_basis: string; lines: BreakdownLine[]; total: number; in_escrow?: boolean; note: string };
  seller_settlement: {
    escrow_vehicle_portion: number;
    commission_rate: number;
    commission: number;
    commission_basis: string;
    net_from_escrow: number;
    direct_remaining: number;
    /** Siapa yang melunasi sisa harga ke penjual. */
    remaining_payer?: "buyer" | "lender" | null;
    total_net: number;
    note: string;
  };
  total_cost_of_ownership: { lines: BreakdownLine[]; total: number };
  /** Rencana kredit leasing yang dipilih (hanya skema kredit). */
  credit?: CreditPlan | null;
  promotions?: {
    lines: PromotionLine[];
    due_now_saving: number;
    total_saving: number;
    note: string;
  };
  credit_simulation: {
    applicable: boolean;
    lender: string;
    note: string;
    principal?: number;
    provision_rate?: number;
    provision_amount?: number;
    admin_fee?: number;
    fiducia_fee?: number;
    min_dp_percent?: number;
    age_surcharge_rate?: number;
    vehicle_age_years?: number | null;
    rows: {
      tenor_months: number;
      flat_rate: number;
      effective_rate?: number;
      interest_total: number;
      monthly_installment: number;
      monthly_installment_with_insurance: number;
      total_payment: number;
    }[];
  };
};

export type CreditPlan = {
  lender: string;
  tenor_months: number;
  vehicle_age_years: number | null;
  base_flat_rate: number;
  age_surcharge_rate: number;
  flat_rate: number;
  rate_basis: string;
  vehicle_financed: number;
  insurance_financed: number;
  principal: number;
  interest_total: number;
  monthly_installment: number;
  total_payable: number;
  effective_rate: number;
  upfront: { lines: BreakdownLine[]; total: number };
  late_fee_daily_rate: number;
  note: string;
};

export type LoanStatus = "submitted" | "approved" | "paid_off";
export type InstallmentStatus = "paid" | "overdue" | "due" | "upcoming";

export type LoanInstallment = {
  id: number;
  vehicle_loan_id: number;
  sequence: number;
  due_date: string;
  principal_amount: string;
  interest_amount: string;
  amount: string;
  status: InstallmentStatus;
  days_late: number;
  late_fee: number;
  amount_due: number;
  paid_at: string | null;
  paid_amount: string | null;
  payment_method: string | null;
  payment_channel: string | null;
  payment_account_masked: string | null;
  payment_approval_code: string | null;
  recorded_by?: string | null;
  note: string | null;
};

export type VehicleLoan = {
  id: number;
  transaction_id: number;
  transaction?: {
    id: number;
    invoice_number: string | null;
    vehicle: string | null;
    buyer: string | null;
    seller: string | null;
    vehicle_price: string | null;
  };
  lender_name: string;
  contract_number: string;
  status: LoanStatus;
  status_label: string;
  vehicle_financed: string;
  insurance_financed: string;
  principal: string;
  flat_rate: string;
  effective_rate: string;
  tenor_months: number;
  interest_total: string;
  monthly_installment: string;
  total_payable: string;
  submitted_at: string;
  auto_approve_at: string;
  approved_at: string | null;
  approved_by?: string | null;
  lender_disbursed_at: string | null;
  paid_off_at: string | null;
  clock_offset_months: number;
  simulated_today: string;
  summary?: {
    paid_count: number;
    outstanding_principal: number;
    outstanding_amount: number;
    overdue_count: number;
    late_fees_due: number;
    next_due_date: string | null;
  };
  installments?: LoanInstallment[];
};

export type PriceQuote = { breakdown: PriceBreakdown; available_addons: InsuranceAddonOption[] };

export type PaymentMethod = "card" | "paypal" | "virtual_account" | "simulation";

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
  bonus_amount?: string;
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
  insurance_addons?: string[];
  insurance_policy_number?: string | null;
  promo_code?: string | null;
  buyer_address: string | null;
  buyer_phone: string | null;
  buyer_notes: string | null;
  invoice_number: string | null;
  bank_transfer_bank: string | null;
  bank_transfer_account_number: string | null;
  bank_transfer_account_holder: string | null;
  payment_gateway: PaymentGateway;
  payment_status: PaymentStatus;
  payment_method?: PaymentMethod | null;
  payment_channel?: string | null;
  payment_account_masked?: string | null;
  payment_payer_name?: string | null;
  payment_approval_code?: string | null;
  gateway_reference: string | null;
  gateway_invoice_url: string | null;
  paid_at: string | null;
  expires_at: string | null;
  escrow_status: EscrowStatus | null;
  handover_requested_at: string | null;
  handover_approved_at: string | null;
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
  price_breakdown?: PriceBreakdown;
  signing_checklist?: SigningChecklistItem[];
  handover_checklist?: HandoverChecklistItem[];
  shipment?: Shipment | null;
  loan?: VehicleLoan | null;
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

export type CatalogPromo = {
  id: number;
  name: string;
  badge: string | null;
  description: string | null;
  discount_amount: number;
  final_price: number;
  ends_at: string | null;
};

export type PromoBenefit = "discount" | "free_addon" | "credit_rate";

export type PromotionLine = {
  promo_id: number;
  name: string;
  code: string | null;
  kind: "auto" | "voucher";
  badge: string | null;
  benefit: PromoBenefit;
  label: string;
  basis: string;
  saving: number;
  in_due_now: boolean;
  applied: boolean;
};

export type Promo = {
  id: number;
  name: string;
  code: string | null;
  kind: "auto" | "voucher";
  benefit: PromoBenefit;
  benefit_summary: string;
  discount_type: "percent" | "fixed" | null;
  discount_value: string | null;
  max_discount: string | null;
  addon_code: string | null;
  rate_cut: string | null;
  min_vehicle_price: string | null;
  brand: string | null;
  badge: string | null;
  description: string | null;
  starts_at: string | null;
  ends_at: string | null;
  quota: number | null;
  used_count: number;
  is_active?: boolean;
  is_running: boolean;
  redemptions_count?: number;
  redemptions_total?: number;
  created_at: string;
};

export type ActivityCategory =
  | "account"
  | "verification"
  | "transaction"
  | "payment"
  | "handover"
  | "document"
  | "shipment"
  | "credit"
  | "payout"
  | "bonus"
  | "promo";

export type ActivityLog = {
  id: number;
  created_at: string;
  actor: { id: number; name: string; role: "admin" | "seller" | "buyer" | null } | null;
  category: ActivityCategory;
  category_label: string;
  action: string;
  description: string;
  subject_type: string | null;
  subject_id: number | null;
  transaction_id: number | null;
  properties: Record<string, unknown> | null;
  ip_address: string | null;
};

export type SellerBonusItem = {
  id: number;
  type: "sale" | "tier";
  tier_threshold: number | null;
  amount: string;
  basis: string;
  status: string;
  paid_at: string | null;
  transaction_id: number | null;
  vehicle: string | null;
};

export type SellerBonusSummary = {
  total_bonus: number;
  completed_sales: number;
  sale_rate: number;
  next_tier: { threshold: number; amount: number; remaining_sales: number } | null;
  tiers: { threshold: number; amount: number; achieved: boolean }[];
};

export type CountRow = { key: string; count: number };

export type AdminReport = {
  range: { from: string; to: string };
  kpis: {
    gmv: number;
    transactions_paid: number;
    transactions_created: number;
    transactions_cancelled: number;
    average_ticket: number;
    escrow_collected: number;
    escrow_held: number;
    commission_revenue: number;
    payouts_paid: number;
    bonuses_paid: number;
    promo_benefit: number;
    loans_active: number;
    loan_outstanding_principal: number;
    installments_collected: number;
    late_fees_collected: number;
    overdue_loans: number;
  };
  monthly: { month: string; transactions: number; gmv: number; commission: number }[];
  escrow_status: CountRow[];
  payment_schemes: { key: string; count: number; gmv: number }[];
  top_brands: { brand: string; count: number; gmv: number }[];
  verification: { seller_kyc: CountRow[]; buyer_kyc: CountRow[]; vehicles: CountRow[] };
  tables: {
    sales: {
      id: number;
      invoice_number: string | null;
      paid_at: string | null;
      vehicle: string | null;
      buyer: string | null;
      seller: string | null;
      scheme: string | null;
      vehicle_price: number;
      amount: number;
      promo_code: string | null;
      escrow_status: string | null;
      payout_status: string | null;
    }[];
    payouts: {
      id: number;
      paid_at: string | null;
      transaction_id: number;
      vehicle: string | null;
      seller: string | null;
      method: string | null;
      commission_amount: number;
      payout_amount: number;
      bonus_amount: number;
      reference: string | null;
    }[];
    bonuses: { id: number; created_at: string | null; seller: string | null; type: string; basis: string; amount: number; transaction_id: number | null }[];
    promos: { id: number; created_at: string | null; promo: string | null; code: string | null; benefit: string | null; buyer: string | null; transaction_id: number; benefit_amount: number }[];
    loans: {
      id: number;
      contract_number: string;
      buyer: string | null;
      vehicle: string | null;
      status: string;
      principal: number;
      tenor_months: number;
      paid_count: number;
      outstanding_principal: number;
      overdue_count: number;
      late_fees_due: number;
    }[];
  };
};
