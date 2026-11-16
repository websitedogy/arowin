export function getExactPackageIdCount(targetAmount, packageAmount) {
  const targetCents = Math.round(Number(targetAmount) * 100)
  const packageCents = Math.round(Number(packageAmount) * 100)

  if (
    !Number.isSafeInteger(targetCents)
    || !Number.isSafeInteger(packageCents)
    || targetCents <= 0
    || packageCents <= 0
    || targetCents % packageCents !== 0
  ) {
    return null
  }

  return targetCents / packageCents
}
