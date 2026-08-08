import { Alert, Button, Stack } from '@mui/material'
import { useState } from 'react'
import { PagBankAccountTypeStep } from './PagBankAccountTypeStep'
import { PagBankBusinessForm, type BusinessFormErrors } from './PagBankBusinessForm'
import { PagBankConnectStep } from './PagBankConnectStep'
import { PagBankPersonForm, type PersonFormErrors } from './PagBankPersonForm'
import { ReceivingReviewStep } from './ReceivingReviewStep'
import { ReceivingSuccessState } from './ReceivingSuccessState'
import * as pagBankConnectService from '../../services/pagBankConnectService'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type {
  CreatePagBankSellerAccountPayload,
  PagBankAccountPersonType,
  PagBankCompanyPayload,
  PagBankPersonPayload,
} from '../../types/pagBankConnect'
import { emptyCompany, emptyPerson } from '../../utils/pagBankDefaults'

type WizardStep = 'connect' | 'account_type' | 'account_form' | 'review' | 'success'

interface ReceivingSetupWizardProps {
  /**
   * `pending_connection` sem conexão ainda concluída reabre direto na
   * escolha Connect/Account — demais status "em andamento"
   * (`pending_submission`) não têm rascunho pra retomar (o backend não
   * persiste formulário parcial nesta sub-fase, roadmap R2.4), então
   * reiniciam do zero a partir da mesma tela de entrada. Limitação
   * documentada, não é regressão.
   */
  initialStep?: WizardStep
  onFinished: () => void
}

function extractFieldError(errors: Record<string, string[]> | undefined, key: string): string | undefined {
  return errors?.[key]?.[0]
}

function buildPersonErrors(errors: Record<string, string[]> | undefined): PersonFormErrors {
  return {
    name: extractFieldError(errors, 'person.name'),
    birth_date: extractFieldError(errors, 'person.birth_date'),
    mother_name: extractFieldError(errors, 'person.mother_name'),
    tax_id: extractFieldError(errors, 'person.tax_id'),
    phone: extractFieldError(errors, 'person.phone.number') ?? extractFieldError(errors, 'person.phone.area'),
    address: {
      street: extractFieldError(errors, 'person.address.street'),
      number: extractFieldError(errors, 'person.address.number'),
      complement: extractFieldError(errors, 'person.address.complement'),
      locality: extractFieldError(errors, 'person.address.locality'),
      city: extractFieldError(errors, 'person.address.city'),
      region_code: extractFieldError(errors, 'person.address.region_code'),
      postal_code: extractFieldError(errors, 'person.address.postal_code'),
    },
  }
}

function buildCompanyErrors(errors: Record<string, string[]> | undefined): BusinessFormErrors {
  return {
    name: extractFieldError(errors, 'company.name'),
    tax_id: extractFieldError(errors, 'company.tax_id'),
    phone: extractFieldError(errors, 'company.phone.number') ?? extractFieldError(errors, 'company.phone.area'),
    address: {
      street: extractFieldError(errors, 'company.address.street'),
      number: extractFieldError(errors, 'company.address.number'),
      complement: extractFieldError(errors, 'company.address.complement'),
      locality: extractFieldError(errors, 'company.address.locality'),
      city: extractFieldError(errors, 'company.address.city'),
      region_code: extractFieldError(errors, 'company.address.region_code'),
      postal_code: extractFieldError(errors, 'company.address.postal_code'),
    },
  }
}

/**
 * Orquestrador do caminho Account/Cadastro (roadmap seção 9.5.2/9.10 R2.4).
 * O caminho Connect (conta existente) é resolvido inteiramente dentro de
 * `PagBankConnectStep` via redirect real — o wizard só avança de estado
 * quando o usuário escolhe "criar conta agora".
 */
export function ReceivingSetupWizard({ initialStep = 'connect', onFinished }: ReceivingSetupWizardProps) {
  const [step, setStep] = useState<WizardStep>(initialStep)
  const [personType, setPersonType] = useState<PagBankAccountPersonType | null>(null)
  const [email, setEmail] = useState('')
  const [person, setPerson] = useState<PagBankPersonPayload>(emptyPerson())
  const [company, setCompany] = useState<PagBankCompanyPayload>(emptyCompany())
  const [termsAccepted, setTermsAccepted] = useState(false)
  const [personErrors, setPersonErrors] = useState<PersonFormErrors>({})
  const [companyErrors, setCompanyErrors] = useState<BusinessFormErrors>({})
  const [emailError, setEmailError] = useState<string | undefined>(undefined)
  const [formStepError, setFormStepError] = useState<string | null>(null)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSubmit() {
    if (!personType) return
    setIsSubmitting(true)
    setSubmitError(null)

    const payload: CreatePagBankSellerAccountPayload = {
      person_type: personType,
      email: email.trim(),
      terms_accepted_at: new Date().toISOString(),
      person,
      ...(personType === 'pj' ? { company } : {}),
    }

    try {
      await pagBankConnectService.createSellerAccount(payload)
      setStep('success')
    } catch (error) {
      if (error instanceof ApiRequestError && Object.keys(error.errors).length > 0) {
        setPersonErrors(buildPersonErrors(error.errors))
        if (personType === 'pj') setCompanyErrors(buildCompanyErrors(error.errors))
        setEmailError(extractFieldError(error.errors, 'email'))
        setFormStepError('Revise os campos destacados abaixo antes de continuar.')
        setStep('account_form')
      } else {
        setSubmitError(getApiErrorMessage(error, 'Não foi possível enviar seus dados agora. Tente novamente em instantes.'))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  if (step === 'connect') {
    return <PagBankConnectStep onChooseCreateAccount={() => setStep('account_type')} />
  }

  if (step === 'account_type') {
    return (
      <PagBankAccountTypeStep
        personType={personType}
        onChangePersonType={setPersonType}
        email={email}
        onChangeEmail={setEmail}
        emailError={emailError}
        onBack={() => setStep('connect')}
        onNext={() => {
          setEmailError(undefined)
          setStep('account_form')
        }}
      />
    )
  }

  if (step === 'account_form') {
    const isAddressFilled = (address: PagBankPersonPayload['address']) =>
      Boolean(address.street && address.number && address.locality && address.city && address.region_code && address.postal_code)
    const isPersonFilled = Boolean(person.name && person.birth_date && person.mother_name && person.tax_id && person.phone.area && person.phone.number) && isAddressFilled(person.address)
    const isCompanyFilled =
      personType !== 'pj' ||
      (Boolean(company.name && company.tax_id && company.phone.area && company.phone.number) && isAddressFilled(company.address))
    const canContinue = isPersonFilled && isCompanyFilled

    return (
      <Stack spacing={3}>
        {formStepError && <Alert severity="warning">{formStepError}</Alert>}
        <PagBankPersonForm
          value={person}
          onChange={setPerson}
          errors={personErrors}
          heading={personType === 'pj' ? 'Responsável legal' : 'Seus dados'}
        />
        {personType === 'pj' && <PagBankBusinessForm value={company} onChange={setCompany} errors={companyErrors} />}
        <Alert severity="info" variant="outlined">
          Na próxima etapa você revisa tudo antes de enviar.
        </Alert>
        <Stack direction="row" spacing={1.5}>
          <Button variant="text" onClick={() => setStep('account_type')}>
            Voltar
          </Button>
          <Button
            variant="contained"
            disabled={!canContinue}
            onClick={() => {
              setFormStepError(null)
              setStep('review')
            }}
          >
            Continuar
          </Button>
        </Stack>
      </Stack>
    )
  }

  if (step === 'review') {
    return (
      <ReceivingReviewStep
        email={email}
        person={person}
        company={personType === 'pj' ? company : undefined}
        termsAccepted={termsAccepted}
        onChangeTermsAccepted={setTermsAccepted}
        onBack={() => setStep('account_form')}
        onSubmit={() => void handleSubmit()}
        isSubmitting={isSubmitting}
        error={submitError}
      />
    )
  }

  return <ReceivingSuccessState variant="account_created" onDone={onFinished} />
}
