<?php

use App\Models\City;
use App\Models\ClergyCategory;
use App\Models\ClergyMember;
use App\Models\Deanery;
use App\Models\Event;
use App\Models\InstituteCategory;
use App\Models\NewsCategory;
use App\Models\Page;
use App\Models\PageCategory;
use App\Models\Parish;
use App\Models\RadioProgram;
use App\Models\RadioStation;
use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function layoutNodes(string $html, string $expression): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $nodes = (new DOMXPath($document))->query($expression);

    return array_map(fn ($node): string => trim($node->textContent), iterator_to_array($nodes));
}

it('keeps only the five clergy categories seminary and memory category in the clergy menu', function (): void {
    $labels = ['Bispos e Arcebispos', 'Padres Diocesanos', 'Padres Religiosos', 'Diáconos', 'Monsenhores'];
    foreach ($labels as $label) {
        ClergyCategory::factory()->create(['title' => $label, 'slug' => Str::slug($label), 'is_active' => true]);
    }
    $memory = PageCategory::factory()->create(['title' => 'Em Memória', 'slug' => 'em-memoria', 'is_active' => true]);
    Page::factory()->create(['title' => 'Seminário', 'slug' => 'seminario', 'menu_group' => 'clero', 'category_id' => null, 'is_active' => true]);
    Page::factory()->create(['title' => 'Uma biografia', 'slug' => 'biography', 'menu_group' => 'clero', 'category_id' => $memory->id, 'is_active' => true]);
    Page::factory()->create(['title' => 'Outra biografia', 'slug' => 'another-biography', 'menu_group' => 'clero', 'category_id' => $memory->id, 'is_active' => true]);
    ClergyCategory::factory()->create(['title' => 'Categoria inativa', 'is_active' => false]);
    $html = $this->get('/')->assertOk()->getContent();
    expect(layoutNodes($html, '//details[@data-menu="clergy"]/div/a'))->toBe([...$labels, 'Seminário', 'Em Memória']);
});

it('groups pages by category and nests orders without flattening their contents', function (): void {
    $synod = PageCategory::factory()->create(['title' => 'Sínodo', 'slug' => 'synod', 'is_active' => true]);
    Page::factory()->create(['title' => 'História', 'slug' => 'historia', 'menu_group' => 'arquidiocese', 'sort_order' => 2, 'category_id' => null, 'is_active' => true]);
    Page::factory()->create(['title' => 'Brasão', 'slug' => 'brasao', 'menu_group' => 'arquidiocese', 'sort_order' => 1, 'category_id' => null, 'is_active' => true]);
    Page::factory()->create(['title' => 'Documento interno', 'menu_group' => 'arquidiocese', 'category_id' => $synod->id, 'is_active' => true]);
    InstituteCategory::factory()->create(['title' => 'Congregações', 'slug' => 'congregations', 'is_active' => true]);
    $html = $this->get('/')->assertOk()->getContent();
    expect(layoutNodes($html, '//details[@data-menu="archdiocese"]/div/a'))->toBe(['Brasão', 'História', 'Sínodo']);
    expect(layoutNodes($html, '//details[@data-menu="archdiocese"]/div/details/summary'))->toBe(['Ordens e Congregações']);
    expect(layoutNodes($html, '//details[@data-menu="archdiocese"]/div/details/div/a'))->toBe(['Congregações']);
});

it('keeps deaneries separate from city filters and includes news pages in their original menu', function (): void {
    Deanery::factory()->create(['name' => 'Forania A', 'slug' => 'deanery-a', 'is_active' => true]);
    City::factory()->create(['name' => 'Cidade A', 'slug' => 'city-a', 'is_active' => true]);
    NewsCategory::factory()->create(['title' => 'Notícias Arquidiocesanas', 'is_active' => true]);
    Page::factory()->create(['title' => 'Informativo', 'menu_group' => 'noticias', 'is_active' => true]);
    $html = $this->get('/')->assertOk()->getContent();
    expect(layoutNodes($html, '//details[@data-menu="deaneries"]/div/a'))->toBe(['Forania A']);
    expect(layoutNodes($html, '//details[@data-menu="news"]/div/a'))->toBe(['Notícias Arquidiocesanas', 'Informativo']);
    expect(layoutNodes($html, '//details[@data-menu="pastoral"]/div/a')[0])->toBe('Agenda Pastoral');
});

it('restores footer links contacts and copyright without invented sections', function (): void {
    SiteSetting::factory()->create(['title' => 'Arquidiocese de Uberaba', 'phone' => '(34) 3312-9565', 'whatsapp' => '34998262556', 'email' => 'contact@example.test', 'address' => 'Praça Dom Eduardo, 56']);
    $html = $this->get('/')->assertOk()->getContent();
    expect(layoutNodes($html, '//footer//h2'))->toBe(['Receba o boletim da Arquidiocese', 'Indicamos', 'Institucional', 'Fale com a Arquidiocese', 'E-mail', 'Endereço']);
    expect(layoutNodes($html, '//section[contains(@class,"footer-institutional")]//a'))->toBe(['Arquidiocese', 'Arcebispo', 'Requisição de Apoio', 'Correio Católico', 'Cúria Metropolitana', 'Chancelaria', 'Tribunal Eclesiástico', 'Pastoral Arquidiocesana', 'Notícias da Arquidiocese', 'Vídeos']);
    expect(layoutNodes($html, '//section[contains(@class,"footer-recommendations")]//a'))->toBe(['CNBB', 'Vatican News', 'Rede Vida']);
    expect($html)->toContain('(34) 99826-2556')->toContain('Copyright ©')->toContain('Todos os Direitos Reservados');
});

it('filters parishes by city and preserves alphabetical listing order', function (): void {
    $city = City::factory()->create(['name' => 'Uberaba', 'slug' => 'uberaba', 'is_active' => true]);
    $other = City::factory()->create(['name' => 'Araxá', 'slug' => 'araxa', 'is_active' => true]);
    Parish::factory()->create(['name' => 'Zeta parish', 'slug' => 'zeta', 'city_id' => $city->id, 'is_active' => true]);
    Parish::factory()->create(['name' => 'Alpha parish', 'slug' => 'alpha', 'city_id' => $city->id, 'is_active' => true]);
    Parish::factory()->create(['name' => 'Other city parish', 'slug' => 'other', 'city_id' => $other->id, 'is_active' => true]);
    $response = $this->get('/paroquias/cidade/uberaba')->assertOk()->assertSeeInOrder(['Alpha parish', 'Zeta parish'])->assertDontSee('Other city parish');
    expect(layoutNodes($response->getContent(), '//aside//a'))->toBe(['Araxá', 'Uberaba']);
    $response->assertSee('aria-current="page"', false);
});

it('preserves category filtering and alphabetical clergy order', function (): void {
    $category = ClergyCategory::factory()->create(['title' => 'Padres Diocesanos', 'slug' => 'padres-diocesanos', 'is_active' => true]);
    ClergyMember::factory()->create(['name' => 'Zeta priest', 'category_id' => $category->id, 'is_active' => true]);
    ClergyMember::factory()->create(['name' => 'Alpha priest', 'category_id' => $category->id, 'is_active' => true]);
    ClergyMember::factory()->create(['name' => 'Inactive priest', 'category_id' => $category->id, 'is_active' => false]);
    ClergyMember::factory()->create(['name' => 'Other category priest', 'category_id' => null, 'is_active' => true]);
    $this->get('/cleros/padres-diocesanos')->assertOk()->assertSeeInOrder(['Alpha priest', 'Zeta priest'])->assertDontSee('Inactive priest')->assertDontSee('Other category priest');
});

it('keeps category pagination usable after entering a legacy numbered page', function (): void {
    $category = ClergyCategory::factory()->create(['title' => 'Padres Diocesanos', 'slug' => 'padres-diocesanos', 'is_active' => true]);
    for ($index = 1; $index <= 45; $index++) {
        ClergyMember::factory()->create(['name' => sprintf('Priest %02d', $index), 'slug' => 'priest-'.$index, 'category_id' => $category->id, 'is_active' => true]);
    }
    $this->get('/cleros/padres-diocesanos/2')->assertOk()->assertSee('Priest 21')->assertDontSee('Priest 01')->assertSee('href="'.url('/cleros/padres-diocesanos/3').'"', false)->assertDontSee('/2?page=3');
    $this->get('/cleros/padres-diocesanos/3')->assertOk()->assertSee('Priest 41')->assertDontSee('Priest 21');
});

it('filters agenda by month and year while retaining all events within that month', function (): void {
    $this->travelTo(now()->setDate(2026, 11, 13));
    Event::factory()->create(['title' => 'Earlier November event', 'slug' => 'earlier', 'starts_on' => '2026-11-02', 'is_active' => true]);
    Event::factory()->create(['title' => 'Later November event', 'slug' => 'later', 'starts_on' => '2026-11-20', 'is_active' => true]);
    Event::factory()->create(['title' => 'December event', 'slug' => 'december', 'starts_on' => '2026-12-02', 'is_active' => true]);
    Event::factory()->create(['title' => 'Next year event', 'slug' => 'next-year', 'starts_on' => '2027-11-02', 'is_active' => true]);
    Event::factory()->create(['title' => 'Inactive event', 'slug' => 'inactive-event', 'starts_on' => '2026-11-15', 'is_active' => false]);
    $this->get('/agenda/11/2026')->assertOk()->assertSee('Novembro de 2026')->assertSee('Earlier November event')->assertSee('Later November event')->assertDontSee('December event')->assertDontSee('Next year event')->assertDontSee('Inactive event');
    $this->get('/agenda/2026')->assertOk()->assertSee('Later November event')->assertSee('December event')->assertDontSee('Earlier November event')->assertDontSee('Next year event');
    $this->travelBack();
});

it('keeps the radio schedule scoped to the local weekday', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-14 01:00:00', 'UTC'));
    $radio = RadioStation::factory()->create(['title' => 'Rádio de teste', 'slug' => 'test-radio', 'is_active' => true]);
    RadioProgram::factory()->create(['radio_station_id' => $radio->id, 'title' => 'Friday program', 'day' => 5, 'is_active' => true]);
    RadioProgram::factory()->create(['radio_station_id' => $radio->id, 'title' => 'Saturday program', 'day' => 6, 'is_active' => true]);
    $this->get('/radio/test-radio')->assertOk()->assertSee('Friday program')->assertDontSee('Saturday program');
    $this->travelBack();
});

it('restores contact information and support form sections', function (): void {
    $this->get('/contato')->assertOk()->assertSee('Entre em contato conosco')->assertSee('INFORMAÇÕES CÚRIA')->assertSee('ASSESSORIA DE IMPRENSA')->assertSee('Secretaria / Chancelaria')->assertSee('Enviar Mensagem');
    $this->get('/solicitar')->assertOk()->assertSeeInOrder(['Dados da Paróquia/Órgão Solicitante', 'Dados da Ação/Evento', 'Materiais de Divulgação', 'Recursos de Comunicação Desejados', 'Prazos', 'Informações Adicionais', 'Anexar Arquivos', 'Termo de Responsabilidade'])->assertSee('type="radio" name="event_type"', false)->assertSee('Enviar Solicitação');
});
