<?php

namespace Tests\Feature\Boq;

use Modules\Boq\Exceptions\BoqImportException;
use Modules\Boq\Models\BoqItem;
use Modules\Boq\Services\BoqImporter;
use Modules\Tenancy\Models\Contract;
use Modules\Tenancy\Models\Site;
use Modules\Tenancy\Support\CompanyContext;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Tenancy\TenancyTestCase;

/**
 * SYNTHETIC fixture: the real Keerom "versi_2_RAPI" workbook was not available when this test was
 * written. This proves the algorithm on a RAPI-like layout; it does NOT prove the real file imports.
 * Swap in the real file (tests/fixtures/boq/keerom_rab.xlsx) and the expected total before the pilot.
 */
class BoqImporterTest extends TenancyTestCase
{
    private function workbook(array $rows, string $sheet = 'RAB'): string
    {
        $wb = new Spreadsheet;
        $ws = $wb->getActiveSheet()->setTitle($sheet);
        $ws->fromArray([['KEMENTERIAN X'], ['Kegiatan: JIAT'], [], ['No', 'Uraian Pekerjaan', 'Satuan', 'Volume', 'Harga Satuan (Rp)', 'Jumlah (Rp)']], null, 'A1');
        $ws->fromArray($rows, null, 'A5');
        $path = tempnam(sys_get_temp_dir(), 'boq').'.xlsx';
        (new Xlsx($wb))->save($path);

        return $path;
    }

    private function contract(string $dpp): Contract
    {
        $c = $this->company('AAA');
        CompanyContext::set($c->id);
        $s = Site::query()->create(['code' => 'KRM', 'name' => 'Keerom']);

        return Contract::query()->create(['site_id' => $s->id, 'number' => 'K-1', 'employer_name' => 'BWS Papua',
            'value_dpp' => $dpp, 'value_total' => $dpp * 1.11]);
    }

    private function rows(): array
    {
        return [
            ['I', 'PEKERJAAN PERSIAPAN', null, null, null, null],
            ['1', 'Mobilisasi', 'LS', 1, 5000000, 5000000],
            ['2', 'Pengukuran', 'titik', 26, 100000, 2600000],
            ['II', 'PEKERJAAN SUMUR', null, null, null, null],
            ['A', 'Pengeboran', null, null, null, null],
            ['1', 'Bor 100 m', 'm', 100, 1250000.50, 125000050],
            ['2', 'Casing', 'm', 50, 300000, 15000000],
            ['B', 'Pompa', null, null, null, null],
            ['1', 'Pompa submersible', 'unit', 2, 30000000, 60000000],
            [null, 'Jumlah', null, null, null, 207600050],
            [null, 'PPN 11%', null, null, null, 22836005.5],
        ];
    }

    public function test_imports_tree_total_and_weights(): void
    {
        $contract = $this->contract('207600050.00');
        $sum = (new BoqImporter)->import($contract, $this->workbook($this->rows()));

        $this->assertSame(9, $sum['items']);
        $this->assertSame(5, $sum['leaves']);
        $this->assertSame('207600050.00', $sum['total']);
        $this->assertSame('0.00', $sum['diff']);
        $this->assertCount(2, $sum['footers']);

        $items = BoqItem::query()->orderBy('sort_order')->get()->keyBy('code');
        $this->assertSame(0, $items['I']->level);
        $this->assertSame(1, $items['I.1']->level);
        $this->assertSame('1', $items['II.A.1']->code_printed);
        $this->assertSame('Bor 100 m', $items['II.A.1']->description);
        $bor = BoqItem::query()->where('description', 'Bor 100 m')->first();
        $this->assertSame(2, $bor->level);
        $this->assertSame('II.A', BoqItem::find($bor->parent_id)->code);
        $this->assertSame('II', BoqItem::find(BoqItem::find($bor->parent_id)->parent_id)->code);

        // level-0 weights sum to exactly 100; parent amount = sum of children
        $this->assertEqualsWithDelta(100.0, (float) BoqItem::query()->where('level', 0)->sum('weight_pct'), 0.000001);
        $this->assertSame('7600000.00', number_format((float) BoqItem::query()->where('code', 'I')->value('amount'), 2, '.', ''));
        $this->assertEqualsWithDelta(100.0, (float) BoqItem::query()->where('is_leaf', true)->sum('weight_pct'), 0.000001);
        // printed jumlah kept to the rupiah-cent (125.000.050 != 100 x 1.250.000,50 only by rounding? it is equal)
        $this->assertSame('125000050.00', number_format((float) $bor->amount, 2, '.', ''));
    }

    public function test_total_mismatch_refuses_and_writes_nothing(): void
    {
        $contract = $this->contract('207600051.00'); // one rupiah off
        try {
            (new BoqImporter)->import($contract, $this->workbook($this->rows()));
            $this->fail('expected BoqImportException');
        } catch (BoqImportException $e) {
            $this->assertStringContainsString('selisih -1.00', $e->getMessage());
        }
        $this->assertSame(0, BoqItem::query()->count());
    }

    public function test_printed_jumlah_wins_over_qty_times_price_and_is_logged(): void
    {
        $rows = [['1', 'Item', 'm', 10, 1000, 10002]]; // 10 x 1000 = 10000 but printed 10002
        $contract = $this->contract('10002.00');
        $sum = (new BoqImporter)->import($contract, $this->workbook($rows));
        $this->assertSame('10002.00', $sum['total']);
        $this->assertSame('amount_mismatch', $sum['anomalies'][0]['type']);
        $this->assertSame(5, $sum['anomalies'][0]['row']);
    }

    public function test_foreign_contract_is_refused(): void
    {
        $contract = $this->contract('10000.00');
        $other = $this->company('BBB');
        CompanyContext::set($other->id);
        $this->expectException(BoqImportException::class);
        (new BoqImporter)->import($contract, $this->workbook([['1', 'Item', 'm', 10, 1000, 10000]]));
    }

    public function test_missing_sheet_and_header(): void
    {
        $contract = $this->contract('10000.00');
        $this->expectException(BoqImportException::class);
        (new BoqImporter)->import($contract, $this->workbook([['1', 'x', 'm', 1, 1, 1]], 'LAIN'));
    }

    public function test_reimport_replaces_items(): void
    {
        $contract = $this->contract('10000.00');
        $path = $this->workbook([['1', 'Item', 'm', 10, 1000, 10000]]);
        (new BoqImporter)->import($contract, $path);
        (new BoqImporter)->import($contract, $path);
        $this->assertSame(1, BoqItem::query()->count());
    }
}
