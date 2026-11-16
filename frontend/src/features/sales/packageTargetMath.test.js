import { describe, expect, it } from 'vitest'
import { getExactPackageIdCount } from './packageTargetMath'

describe('getExactPackageIdCount', () => {
  it('returns the exact number of package IDs for a divisible target', () => {
    expect(getExactPackageIdCount('1000.00', '50.00')).toBe(20)
    expect(getExactPackageIdCount('150.00', '150.00')).toBe(1)
  })

  it('returns null when the package price cannot exactly match the target', () => {
    expect(getExactPackageIdCount('1000.00', '150.00')).toBeNull()
  })

  it('returns null for invalid or zero amounts', () => {
    expect(getExactPackageIdCount('0.00', '50.00')).toBeNull()
    expect(getExactPackageIdCount('1000.00', '0.00')).toBeNull()
    expect(getExactPackageIdCount('invalid', '50.00')).toBeNull()
  })
})
