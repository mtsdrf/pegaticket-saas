import type { PagBankAddressPayload, PagBankCompanyPayload, PagBankPersonPayload } from '../types/pagBankConnect'

export function emptyAddress(): PagBankAddressPayload {
  return { street: '', number: '', complement: '', locality: '', city: '', region_code: '', postal_code: '', country: 'BRA' }
}

export function emptyPerson(): PagBankPersonPayload {
  return {
    name: '',
    birth_date: '',
    mother_name: '',
    tax_id: '',
    address: emptyAddress(),
    phone: { country: '55', area: '', number: '' },
  }
}

export function emptyCompany(): PagBankCompanyPayload {
  return {
    name: '',
    tax_id: '',
    address: emptyAddress(),
    phone: { country: '55', area: '', number: '' },
  }
}
