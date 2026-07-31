export const FORM_FIELD_SURFACE_SX = {
  '& .MuiTextField-root .MuiOutlinedInput-root:not(.MuiInputBase-multiline), & .MuiFormControl-root .MuiOutlinedInput-root:not(.MuiInputBase-multiline)':
    {
      minHeight: 44,
    },
  '& .MuiAutocomplete-root .MuiOutlinedInput-root:not(.MuiInputBase-multiline)': {
    minHeight: 44,
  },
  '& .MuiTextField-root .MuiOutlinedInput-input, & .MuiFormControl-root .MuiSelect-select': {
    paddingTop: '10px',
    paddingBottom: '10px',
  },
  '& .MuiAutocomplete-root .MuiOutlinedInput-input': {
    paddingTop: '10px',
    paddingBottom: '10px',
  },
  '& .MuiTextField-root .MuiInputLabel-outlined, & .MuiFormControl-root .MuiInputLabel-outlined': {
    transform: 'translate(14px, 11px) scale(1)',
  },
  '& .MuiAutocomplete-root .MuiInputLabel-outlined': {
    transform: 'translate(14px, 11px) scale(1)',
  },
  '& .MuiTextField-root .MuiInputLabel-shrink, & .MuiFormControl-root .MuiInputLabel-shrink': {
    transform: 'translate(14px, -9px) scale(0.75)',
  },
  '& .MuiAutocomplete-root .MuiInputLabel-shrink': {
    transform: 'translate(14px, -9px) scale(0.75)',
  },
} as const
