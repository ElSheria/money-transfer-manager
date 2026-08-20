<?php

namespace Database\Seeders;

use App\Models\Agency;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->agencies() as $agency) {
            Agency::query()->updateOrCreate(
                ['code' => $agency['code']],
                [
                    'name' => $agency['name'],
                    'province' => $agency['province'] ?? 'A renseigner',
                    'city' => $agency['city'] ?? 'A renseigner',
                    'commune' => $agency['commune'] ?? null,
                    'is_active' => true,
                ],
            );
        }
    }

    private function agencies(): array
    {
        return [
            ['name' => 'ANKORO', 'code' => 'ANK'],
            ['name' => 'KABINDA', 'code' => 'KAB'],
            ['name' => 'KALAMBA MBUJI', 'code' => 'KALMBU'],
            ['name' => 'KALEMIE', 'code' => 'KAL'],
            ['name' => 'KASAJI', 'code' => 'KAS'],
            ['name' => 'KASENGA', 'code' => 'KASE'],
            ['name' => 'KASUMBALESA', 'code' => 'KASUM'],
            ['name' => 'KINSHASA MASINA', 'code' => 'KINMA', 'province' => 'Kinshasa', 'city' => 'Kinshasa', 'commune' => 'Masina'],
            ['name' => 'KISANFU', 'code' => 'KISAN'],
            ['name' => 'KOLWEZI MUTOSHI', 'code' => 'KOLMUT', 'city' => 'Kolwezi'],
            ['name' => 'KOLWEZI VILLE', 'code' => 'KOLVIL', 'city' => 'Kolwezi'],
            ['name' => 'LUBUMBASHI TEXACO', 'code' => 'LUBTEX', 'city' => 'Lubumbashi', 'commune' => 'Texaco'],
            ['name' => 'MBUJIMAYI KALALA WANKATA', 'code' => 'MBUKW', 'city' => 'Mbujimayi'],
            ['name' => 'NGANDAJIKA', 'code' => 'NGA'],
            ['name' => 'TENKE', 'code' => 'TEN'],
            ['name' => 'BUKAMA', 'code' => 'BUK'],
            ['name' => 'KABONDO DIANDA', 'code' => 'KABDI'],
            ['name' => 'KAMBOVE', 'code' => 'KAMBO'],
            ['name' => 'KINSHASA VICTOIRE', 'code' => 'KINVIC', 'province' => 'Kinshasa', 'city' => 'Kinshasa', 'commune' => 'Victoire'],
            ['name' => 'KISENGE MANGANEZE', 'code' => 'KISMAN'],
            ['name' => 'KOLWEZI MWANGEJI', 'code' => 'KOLMWA', 'city' => 'Kolwezi'],
            ['name' => 'LIKASI VILLE', 'code' => 'LIKVI', 'city' => 'Likasi'],
            ['name' => 'LUBUMBASHI SENDWE', 'code' => 'LUBSEN', 'city' => 'Lubumbashi', 'commune' => 'Sendwe'],
            ['name' => 'LUENA', 'code' => 'LUE'],
            ['name' => 'MANONO', 'code' => 'MAN'],
            ['name' => 'MITWABA CITE', 'code' => 'MITCI'],
            ['name' => 'MWENEDITU', 'code' => 'MWE'],
            ['name' => 'NYUZU', 'code' => 'NYU'],
            ['name' => 'SANDOA', 'code' => 'SAN'],
            ['name' => 'TSHIKAPA', 'code' => 'TSH'],
            ['name' => 'DIBELE', 'code' => 'DIB'],
            ['name' => 'KABONGO KIME', 'code' => 'KABKI'],
            ['name' => 'KAMINA CITE', 'code' => 'KAMCI', 'city' => 'Kamina'],
            ['name' => 'KANANGA', 'code' => 'KAN'],
            ['name' => 'KILWA', 'code' => 'KIL'],
            ['name' => 'KINSHASA ZANDO', 'code' => 'KINZAN', 'province' => 'Kinshasa', 'city' => 'Kinshasa', 'commune' => 'Zando'],
            ['name' => 'KITENGE GARE', 'code' => 'KITGA'],
            ['name' => 'KOLWEZI OKITO', 'code' => 'KOLOKI', 'city' => 'Kolwezi'],
            ['name' => 'LUBUDI', 'code' => 'LUBU'],
            ['name' => 'LUBUMBASHI KENYA', 'code' => 'LUBKEN', 'city' => 'Lubumbashi', 'commune' => 'Kenya'],
            ['name' => 'LUSAMBO', 'code' => 'LUS'],
            ['name' => 'MOBA', 'code' => 'MOB'],
            ['name' => 'MULONGO', 'code' => 'MUL'],
            ['name' => 'PWETO', 'code' => 'PWE'],
            ['name' => 'TSHUMBE', 'code' => 'TSHU'],
            ['name' => 'DILOLO', 'code' => 'DIL'],
            ['name' => 'FUNGURUME BASE', 'code' => 'FUNBA'],
            ['name' => 'KAMINA VILLE', 'code' => 'KAMVI', 'city' => 'Kamina'],
            ['name' => 'KINKONDJA MANGI', 'code' => 'KINMAN'],
            ['name' => 'KOLWEZI CITE', 'code' => 'KOLCI', 'city' => 'Kolwezi'],
            ['name' => 'KONGOLO', 'code' => 'KON'],
            ['name' => 'KYOLO', 'code' => 'KYO'],
            ['name' => 'LODJA', 'code' => 'LOD'],
            ['name' => 'LUBUMBASHI MOERO', 'code' => 'LUBMOE', 'city' => 'Lubumbashi', 'commune' => 'Moero'],
            ['name' => 'MALEMBA', 'code' => 'MAL'],
            ['name' => 'MBUJIMAYI BAKWADIANGA', 'code' => 'MBUBAK', 'city' => 'Mbujimayi'],
            ['name' => 'MOKAMBO', 'code' => 'MOK'],
            ['name' => 'SAKANIA', 'code' => 'SAK'],
            ['name' => 'SUMBULA', 'code' => 'SUM'],
            ['name' => 'WIKONG', 'code' => 'WIK'],
            ['name' => 'LUBUMBASHI NJANJA', 'code' => 'LUBNJA', 'city' => 'Lubumbashi', 'commune' => 'Nwanja'],
            ['name' => 'KAKANDA', 'code' => 'KAKA'],
            ['name' => 'KANYAMA', 'code' => 'KANY'],
            ['name' => 'KINSHASA GOMBE', 'code' => 'KINGOM', 'province' => 'Kinshasa', 'city' => 'Kinshasa', 'commune' => 'Gombe'],
            ['name' => 'KINSHASA KITAMBO', 'code' => 'KINKIT', 'province' => 'Kinshasa', 'city' => 'Kinshasa', 'commune' => 'Kitambo'],
            ['name' => 'KOLWEZI KAPATA', 'code' => 'KOLKAP', 'city' => 'Kolwezi'],
            ['name' => 'KABALO', 'code' => 'KABA'],
            ['name' => 'KOLWEZI LUILU', 'code' => 'KOLLUI', 'city' => 'Kolwezi'],
            ['name' => 'LIKASI CITE', 'code' => 'LIKCI', 'city' => 'Likasi'],
            ['name' => 'MUSUMBA', 'code' => 'MUS'],
            ['name' => 'NKOLE', 'code' => 'NKO'],
        ];
    }
}
