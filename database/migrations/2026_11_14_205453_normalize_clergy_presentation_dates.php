<?php

use App\Support\PresentationDate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasOriginal = Schema::hasColumn('clergy_members', 'presentation_date_text');
        $dates = [];
        if ($hasOriginal) {
            if (Schema::hasColumn('clergy_members', 'legacy_presentation_date_text')) {
                throw new RuntimeException('Ambiguous presentation date archive; conversion stopped.');
            }
            foreach (DB::table('clergy_members')->get(['id', 'presentation_date_text']) as $record) {
                $dates[$record->id] = $this->normalized($record->id, $record->presentation_date_text);
            }
        }
        if (! Schema::hasColumn('clergy_members', 'presentation_date')) {
            Schema::table('clergy_members', fn (Blueprint $table) => $table->date('presentation_date')->nullable());
        }
        if ($hasOriginal) {
            DB::transaction(function () use ($dates): void {
                foreach ($dates as $id => $date) {
                    DB::table('clergy_members')->where('id', $id)->update(['presentation_date' => $date]);
                }
            });
            foreach (DB::table('clergy_members')->get(['id', 'presentation_date']) as $record) {
                if (! array_key_exists($record->id, $dates) || $record->presentation_date !== $dates[$record->id]) {
                    throw new RuntimeException('Presentation date conversion verification failed.');
                }
            }
            Schema::table('clergy_members', fn (Blueprint $table) => $table->renameColumn('presentation_date_text', 'legacy_presentation_date_text'));
        } elseif (! Schema::hasColumn('clergy_members', 'legacy_presentation_date_text')) {
            Schema::table('clergy_members', fn (Blueprint $table) => $table->string('legacy_presentation_date_text', 1024)->nullable());
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('clergy_members', 'presentation_date') || ! Schema::hasColumn('clergy_members', 'legacy_presentation_date_text')) {
            return;
        }
        $values = [];
        foreach (DB::table('clergy_members')->get(['id', 'presentation_date', 'legacy_presentation_date_text']) as $record) {
            $current = $record->presentation_date === null ? null : PresentationDate::toIso(substr($record->presentation_date, 0, 10));
            $original = $this->normalized($record->id, $record->legacy_presentation_date_text);
            $values[$record->id] = $current === $original ? $record->legacy_presentation_date_text : ($current ? DateTimeImmutable::createFromFormat('!Y-m-d', $current)->format('d/m/Y') : null);
        }
        DB::transaction(function () use ($values): void {
            foreach ($values as $id => $value) {
                DB::table('clergy_members')->where('id', $id)->update(['legacy_presentation_date_text' => $value]);
            }
        });
        Schema::table('clergy_members', function (Blueprint $table): void {
            $table->dropColumn('presentation_date');
            $table->renameColumn('legacy_presentation_date_text', 'presentation_date_text');
        });
    }

    private function normalized(int $id, ?string $value): ?string
    {
        if ($id === 168 && $value === 'dsafda') {
            return null;
        }

        return PresentationDate::toIso($value);
    }
};
