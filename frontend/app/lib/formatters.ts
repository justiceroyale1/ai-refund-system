export function formatMachineValue(value: string): string {
  const normalizedValue = value.split('_').filter(Boolean).join(' ')

  return normalizedValue.charAt(0).toUpperCase() + normalizedValue.slice(1)
}

export function formatCurrencyCents(amountCents: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amountCents / 100)
}

export function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}
