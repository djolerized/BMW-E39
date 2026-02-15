import { supabase } from './supabase';

export interface IPSQRParams {
  receiverAccount: string;
  receiverName: string;
  amount: number;
  paymentPurpose: string;
  paymentReference: string;
  currency?: string;
  paymentCode?: string;
  payerName?: string;
  payerAddress?: string;
}

export interface IPSQRResult {
  success: boolean;
  qrCodeImage?: string;
  qrDataString: string;
  error?: string;
  validationErrors?: string[];
}

const NBS_API_BASE = 'https://nbs.rs/QRcode/api/qr/v1';

export function preValidateIPSQRData(params: IPSQRParams): {
  valid: boolean;
  errors: string[];
} {
  const errors: string[] = [];

  if (!validateAccountNumber(params.receiverAccount)) {
    errors.push('Neispravan broj računa primaoca (mod 97 validacija nije prošla)');
  }

  if (!/^\d{2}/.test(params.paymentReference)) {
    errors.push('Poziv na broj mora počinjati sa dve cifre');
  }

  if (params.amount <= 0) {
    errors.push('Iznos mora biti veći od 0');
  }

  if (!params.receiverName || params.receiverName.trim().length === 0) {
    errors.push('Ime primaoca je obavezno');
  }

  if (!params.paymentPurpose || params.paymentPurpose.trim().length === 0) {
    errors.push('Svrha plaćanja je obavezna');
  }

  return {
    valid: errors.length === 0,
    errors
  };
}

async function generateIPSQRCodeWithAPIInternal(params: IPSQRParams, qrDataString: string): Promise<IPSQRResult> {
  const { data: { session } } = await supabase.auth.getSession();
  const accessToken = session?.access_token;

  if (!accessToken) {
    throw new Error('Korisnik nije autentifikovan');
  }

  const supabaseUrl = import.meta.env.VITE_SUPABASE_URL;

  if (!supabaseUrl) {
    console.error('VITE_SUPABASE_URL environment variable is not set');
    throw new Error('Konfiguracija nije ispravna. Kontaktirajte administratora.');
  }

  const apiUrl = `${supabaseUrl}/functions/v1/generate-ips-qr`;

  const response = await fetch(apiUrl, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${accessToken}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ qrDataString })
  });

  if (!response.ok) {
    throw new Error(`Edge Function returned status ${response.status}`);
  }

  const result = await response.json();

  if (result.s?.code === 0 && result.i) {
    return {
      success: true,
      qrCodeImage: result.i,
      qrDataString: result.t || qrDataString
    };
  } else {
    return {
      success: false,
      qrDataString,
      error: result.s?.desc || 'Nepoznata greška pri generisanju QR koda',
      validationErrors: result.e
    };
  }
}

export async function generateIPSQRCodeWithAPI(params: IPSQRParams, maxRetries = 3): Promise<IPSQRResult> {
  const validation = preValidateIPSQRData(params);

  if (!validation.valid) {
    const qrDataString = '';
    return {
      success: false,
      qrDataString,
      error: 'Greške u validaciji podataka',
      validationErrors: validation.errors
    };
  }

  const {
    receiverAccount,
    receiverName,
    amount,
    paymentPurpose,
    paymentReference,
    currency = 'RSD',
    paymentCode = '289',
    payerName,
    payerAddress
  } = params;

  const formattedAmount = `${currency}${amount.toFixed(2).replace('.', ',')}`;
  const cleanAccount = receiverAccount.replace(/-/g, '');

  const qrDataObject: Record<string, string> = {
    K: 'PR',
    V: '01',
    C: '1',
    R: cleanAccount,
    N: receiverName,
    I: formattedAmount,
    SF: paymentCode,
    S: paymentPurpose,
    RO: paymentReference
  };

  if (payerName && payerAddress) {
    qrDataObject.P = `${payerName}\r\n${payerAddress}`;
  } else if (payerName) {
    qrDataObject.P = payerName;
  }

  const qrDataString = Object.entries(qrDataObject)
    .map(([key, value]) => `${key}:${value}`)
    .join('|');

  let lastError: IPSQRResult | null = null;

  for (let attempt = 1; attempt <= maxRetries; attempt++) {
    try {
      return await generateIPSQRCodeWithAPIInternal(params, qrDataString);
    } catch (error) {
      console.error(`QR generation attempt ${attempt}/${maxRetries} failed:`, error);

      lastError = {
        success: false,
        qrDataString,
        error: error instanceof Error ? error.message : 'Greška pri komunikaciji sa serverom'
      };

      if (error instanceof Error && error.message.includes('nije autentifikovan')) {
        break;
      }

      if (attempt < maxRetries) {
        await new Promise(resolve => setTimeout(resolve, 1000 * attempt));
      }
    }
  }

  return lastError || {
    success: false,
    qrDataString,
    error: 'Neuspešno generisanje nakon više pokušaja'
  };
}

export function generateIPSQRCode(params: IPSQRParams): string {
  const {
    receiverAccount,
    receiverName,
    amount,
    paymentPurpose,
    paymentReference,
    currency = 'RSD',
    paymentCode = '289'
  } = params;

  const formattedAmount = amount.toFixed(2);
  const cleanAccount = receiverAccount.replace(/-/g, '');

  const qrData = [
    'K:PR',
    'V:01',
    'C:1',
    `R:${cleanAccount}`,
    `N:${receiverName}`,
    `I:${currency}${formattedAmount}`,
    `SF:${paymentCode}`,
    `S:${paymentPurpose}`,
    `RO:${paymentReference}`
  ].join('|');

  return qrData;
}

export async function validateIPSQRCode(qrDataString: string): Promise<{
  valid: boolean;
  errors?: string[];
  parsedData?: Record<string, string>;
}> {
  try {
    const response = await fetch(`${NBS_API_BASE}/validate?lang=sr_RS_Latn`, {
      method: 'POST',
      headers: {
        'Content-Type': 'text/plain',
      },
      body: qrDataString
    });

    if (!response.ok) {
      throw new Error(`NBS API returned status ${response.status}`);
    }

    const result = await response.json();

    if (result.s?.code === 0) {
      return {
        valid: true,
        parsedData: result.n
      };
    } else {
      return {
        valid: false,
        errors: result.e || [result.s?.desc || 'Validacija nije prošla']
      };
    }
  } catch (error) {
    console.error('Failed to validate IPS QR code:', error);
    return {
      valid: false,
      errors: ['Greška pri validaciji QR koda']
    };
  }
}

export function parseIPSQRCode(qrData: string): Partial<IPSQRParams> | null {
  try {
    const parts = qrData.split('|');
    const result: Record<string, string> = {};

    parts.forEach(part => {
      const [key, ...valueParts] = part.split(':');
      const value = valueParts.join(':');
      result[key] = value;
    });

    if (!result.R || !result.N || !result.I) {
      return null;
    }

    const amountMatch = result.I.match(/RSD(.+)/);
    const amount = amountMatch ? parseFloat(amountMatch[1].replace(',', '.')) : 0;

    return {
      receiverAccount: result.R,
      receiverName: result.N,
      amount,
      paymentPurpose: result.S || '',
      paymentReference: result.RO || '',
      currency: 'RSD',
      paymentCode: result.SF || '289'
    };
  } catch (error) {
    console.error('Failed to parse IPS QR code:', error);
    return null;
  }
}

export function formatAccountNumber(account: string): string {
  const clean = account.replace(/\D/g, '');

  if (clean.length === 18) {
    return `${clean.slice(0, 3)}-${clean.slice(3, 16)}-${clean.slice(16)}`;
  }

  return account;
}

export function validateAccountNumber(account: string): boolean {
  const clean = account.replace(/\D/g, '');

  if (clean.length !== 18) {
    return false;
  }

  const checkDigit = parseInt(clean.slice(-2), 10);
  const accountPart = clean.slice(0, 16);

  const mod97 = BigInt(accountPart) % 97n;
  const calculatedCheck = 98 - Number(mod97);

  return calculatedCheck === checkDigit;
}
