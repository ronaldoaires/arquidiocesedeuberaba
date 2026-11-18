<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PROFILE_FIELDS = ['type', 'tax_id', 'company_tax_id', 'identity_document', 'gender', 'marital_status', 'birthday', 'area_code', 'phone', 'is_devotee', 'mobile', 'postal_code', 'state', 'city', 'street', 'number', 'address_complement', 'neighborhood'];

    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            foreach (self::PROFILE_FIELDS as $field) {
                if ($field === 'birthday') {
                    if (! Schema::hasColumn('user_profiles', $field)) {
                        $table->date($field)->nullable();
                    }
                } else {
                    if (Schema::hasColumn('user_profiles', $field)) {
                        $table->text($field)->nullable()->change();
                    } else {
                        $table->text($field)->nullable();
                    }
                }
            }
        });
        foreach (DB::table('user_profiles')->get() as $profile) {
            $data = json_decode($profile->profile_data, true, flags: JSON_THROW_ON_ERROR);
            DB::table('user_profiles')->where('id', $profile->id)->update(array_intersect_key($data, array_flip(self::PROFILE_FIELDS)));
        }
        Schema::create('clergy_legacy_affiliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clergy_member_id')->constrained('clergy_members')->restrictOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->foreignId('parish_id')->nullable()->constrained('parishes')->restrictOnDelete();
            $table->text('parish_text')->nullable();
            $table->text('link')->nullable();
            $table->text('role_text')->nullable();
            $table->unique(['clergy_member_id', 'slot']);
        });
        foreach (DB::table('clergy_members')->get() as $clergy) {
            foreach ([1, 2, 3] as $slot) {
                $text = $clergy->{'parish_'.$slot.'_text'};
                $link = $clergy->{'parish_'.$slot.'_link'};
                $role = $clergy->{'role_'.$slot.'_text'};
                if (blank($text) && blank($link) && blank($role)) {
                    continue;
                }
                $parishId = null;
                $host = parse_url($link ?? '', PHP_URL_HOST);
                if ((! $host || in_array(strtolower($host), ['localhost', 'arquidiocesedeuberaba.com.br', 'www.arquidiocesedeuberaba.com.br', 'arquidiocesedeuberaba.org.br', 'www.arquidiocesedeuberaba.org.br'], true)) && preg_match('~/paroquia/([^/?#]+)~', $link ?? '', $match)) {
                    $parishId = DB::table('parishes')->where('slug', rawurldecode($match[1]))->value('id');
                }
                DB::table('clergy_legacy_affiliations')->insert(['clergy_member_id' => $clergy->id, 'slot' => $slot, 'parish_id' => $parishId, 'parish_text' => $text, 'link' => $link, 'role_text' => $role]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clergy_legacy_affiliations');
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn(self::PROFILE_FIELDS);
        });
    }
};
