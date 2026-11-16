<?php

declare(strict_types=1);

namespace Arowin\Modules\Territories;

final class IndiaTerritories
{
    private static ?array $states = null;

    public static function all(): array
    {
        if (self::$states !== null) {
            return self::$states;
        }

        $dataPath = dirname(__DIR__, 2) . '/data/india_states_districts.json';
        $contents = file_get_contents($dataPath);
        if ($contents === false) {
            throw new \RuntimeException('The India state and district data could not be read.');
        }

        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data['states'] ?? null)) {
            throw new \RuntimeException('The India state and district data is invalid.');
        }

        $states = [];
        foreach ($data['states'] as $state) {
            if (
                !is_array($state)
                || !is_string($state['code'] ?? null)
                || !is_string($state['name'] ?? null)
                || !is_array($state['districts'] ?? null)
            ) {
                throw new \RuntimeException('The India state and district data contains an invalid entry.');
            }

            $districts = array_values(array_filter(
                $state['districts'],
                static fn (mixed $district): bool => is_string($district) && trim($district) !== ''
            ));

            $states[] = [
                'code' => $state['code'],
                'name' => $state['name'],
                'districts' => $districts,
            ];
        }

        self::$states = $states;

        return self::$states;
    }

    public static function findState(string $code): ?array
    {
        $code = strtoupper(trim($code));
        foreach (self::all() as $state) {
            if ($state['code'] === $code) {
                return $state;
            }
        }

        return null;
    }

    public static function findDistrict(string $stateCode, string $districtName): ?string
    {
        $state = self::findState($stateCode);
        if ($state === null) {
            return null;
        }

        $districtName = trim($districtName);
        foreach ($state['districts'] as $district) {
            if (strcasecmp($district, $districtName) === 0) {
                return $district;
            }
        }

        return null;
    }
}
