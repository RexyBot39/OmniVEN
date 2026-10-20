<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Task;
use App\Models\TaskbuddyRun;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class TaskBuddyTest extends TestCase
{
    use RefreshDatabase;

    private User $kay;
    private User $leona;
    private array $requests = [];
    private int $status = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        config(['assistant.key' => 'sk-test', 'assistant.model' => 'gpt-6.1-sol', 'assistant.effort' => 'high']);
        $this->kay = User::factory()->create(['name' => 'Kay Lee', 'email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->leona = User::factory()->create(['name' => 'Leona Park', 'email' => 'leona.test@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    /** OpenAI answers with $first, then (once answers or changes come back) with $second. */
    private function fakeOpenAi(array $first, ?array $second = null): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $r) use ($first, $second) {
            $this->requests[] = $r;
            if ($this->status !== 200) {
                $status = $this->status;

                return Http::response(['error' => ['message' => 'slow down']], $status);
            }
            $text = collect($r['input'][0]['content'])->firstWhere('type', 'input_text')['text'];
            $out = $second && str_contains($text, 'Your previous draft') ? $second : $first;

            return Http::response(['status' => 'completed', 'usage' => ['input_tokens' => 1000, 'output_tokens' => 400],
                'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($out)]]]]]);
        });
    }

    private function task(array $over = []): array
    {
        return $over + ['title' => 'Update the safety-glasses landing page', 'details' => "- New hero\n- Add Z87 badge", 'assignee_email' => null, 'brand' => 'ocusafe',
            'due_on' => '2026-11-10', 'hotlist' => false, 'campaign_id' => null, 'group' => 'Website', 'source_quote' => 'update the landing page by Nov 10'];
    }

    public function test_it_lives_inside_ask_omniven_ai_once_switched_on(): void
    {
        config(['assistant.key' => null]);
        $this->actingAs($this->kay)->get('/assistant/taskbuddy')->assertForbidden();
        $this->assertStringNotContainsString('>TaskBuddy AI</a>', $this->actingAs($this->kay)->get('/help')->getContent(), 'not in the menu until switched on');

        config(['assistant.key' => 'sk-test']);
        $html = $this->actingAs($this->kay)->get('/assistant')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('taskbuddy').'" aria-pressed="false">TaskBuddy AI</a>', $html);
        $html = $this->actingAs($this->kay)->get('/assistant/taskbuddy')->assertOk()->getContent();
        $this->assertStringContainsString('Make my task list', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertMatchesRegularExpression('#>TaskBuddy AI</a>#', $html, 'in the Work menu');
    }

    public function test_pasted_notes_become_questions_then_a_list_that_is_added_with_every_field(): void
    {
        $campaign = Campaign::create(['name' => 'Ocusafe winter launch', 'tag' => 'ocusafe-winter', 'brand_id' => Brand::where('slug', 'ocusafe')->value('id'), 'starts_on' => '2026-11-01', 'ends_on' => '2026-12-15']);
        $this->fakeOpenAi(
            ['title' => 'Ocusafe winter launch', 'summary' => 'Kickoff notes: website and email work.', 'questions' => [
                ['id' => 'who_email', 'question' => 'Who writes the launch emails?', 'why' => 'Sets the owner', 'choices' => ['Kay', 'Leona']],
            ], 'tasks' => [$this->task()]],
            ['title' => 'Ocusafe winter launch', 'summary' => 'Website and two emails.', 'questions' => [], 'tasks' => [
                $this->task(['campaign_id' => $campaign->id]),
                $this->task(['title' => 'Write launch email 1', 'assignee_email' => 'LEONA.TEST@39dollarglasses.com', 'due_on' => '2026-11-12', 'hotlist' => true, 'group' => 'Email', 'campaign_id' => $campaign->id]),
                $this->task(['title' => 'Write launch email 2', 'assignee_email' => 'nobody@else.com', 'brand' => 'nonsense', 'due_on' => 'next week', 'campaign_id' => 999, 'group' => 'Email']),
            ]],
        );

        $notes = "Kickoff: update the landing page by Nov 10. Two launch emails. Wifi password: hunter2";
        $res = $this->actingAs($this->kay)->post('/assistant/taskbuddy', ['text' => $notes, 'note' => 'Only the launch work']);
        $run = TaskbuddyRun::firstOrFail();
        $res->assertRedirect(route('taskbuddy.show', $run));

        // One structured-output request: the notes as data, people and brands as context, nothing stored at OpenAI.
        $r = $this->requests[0];
        $this->assertSame(['gpt-6.1-sol', 'high', false], [$r['model'], $r['reasoning']['effort'], $r['store']]);
        $this->assertSame(['json_schema', true], [$r['text']['format']['type'], $r['text']['format']['strict']]);
        $text = $r['input'][0]['content'][0]['text'];
        $this->assertStringContainsString($notes, $text);
        $this->assertStringContainsString('Only the launch work', $text);
        $this->assertStringContainsString('Leona Park <leona.test@39dollarglasses.com>', $r['instructions']);
        $this->assertStringContainsString('id '.$campaign->id.': Ocusafe winter launch', $r['instructions']);
        $this->assertStringContainsString('today is Friday, October 16, 2026', $r['instructions']);

        // The source is encrypted at rest; activity says only that TaskBuddy was used.
        $this->assertStringNotContainsString('landing page', (string) DB::table('taskbuddy_runs')->value('source_text'));
        $this->assertSame(0, DB::table('activity_logs')->where('description', 'like', '%landing%')->count());

        $run->refresh();
        $this->assertSame(['questions', 'Ocusafe winter launch'], [$run->status, $run->title]);
        $html = $this->actingAs($this->kay)->get(route('taskbuddy.show', $run))->assertOk()->getContent();
        $this->assertStringContainsString('Who writes the launch emails?', $html);
        $this->assertStringContainsString('value="Leona"', $html);
        $this->assertStringContainsString('Skip, review the draft (1 task)', $html);

        // Answers go back with the previous draft; a typed answer wins over a picked choice.
        $this->actingAs($this->kay)->post(route('taskbuddy.answer', $run), ['answers' => ['who_email' => 'Kay'], 'others' => ['who_email' => 'Leona writes both']])->assertRedirect();
        $text = collect($this->requests[1]['input'][0]['content'])->firstWhere('type', 'input_text')['text'];
        $this->assertStringContainsString("Q: Who writes the launch emails?\nA: Leona writes both", $text);
        $this->assertStringContainsString('Update the safety-glasses landing page', $text);

        $run->refresh();
        $this->assertSame('ready', $run->status);
        // Fields are checked against OmniVEN: unknown people, brands, dates and campaigns fall back.
        [$a, $b, $c] = $run->draft;
        $ocusafe = Brand::where('slug', 'ocusafe')->value('id');
        $this->assertSame([$this->kay->id, $ocusafe, '2026-11-10', $campaign->id], [$a['assignee_id'], $a['brand_id'], $a['due_on'], $a['campaign_id']]);
        $this->assertSame([$this->leona->id, true], [$b['assignee_id'], $b['hot']]);
        $this->assertSame([$this->kay->id, null, null, null], [$c['assignee_id'], $c['brand_id'], $c['due_on'], $c['campaign_id']]);

        $html = $this->actingAs($this->kay)->get(route('taskbuddy.show', $run))->assertOk()->getContent();
        $this->assertStringContainsString('Review 3 tasks', $html);
        $this->assertStringContainsString('<h3 class="tb-group">Email</h3>', $html);
        $this->assertStringContainsString('value="2026-11-12"', $html);

        // Keep two (one edited), drop the third.
        $form = ['tasks' => [
            ['keep' => '1', 'title' => 'Update the Ocusafe landing page', 'details' => $a['details'], 'assignee_id' => $this->kay->id, 'brand_id' => $ocusafe, 'due_on' => '2026-11-10', 'campaign_id' => $campaign->id],
            ['keep' => '1', 'title' => $b['title'], 'details' => '', 'assignee_id' => $this->leona->id, 'brand_id' => $ocusafe, 'due_on' => '2026-11-12', 'hot' => '1', 'campaign_id' => $campaign->id],
            ['title' => $c['title'], 'assignee_id' => $this->kay->id],
        ]];
        $this->actingAs($this->kay)->post(route('taskbuddy.add', $run), $form)
            ->assertRedirect(route('dashboard').'#tasks')->assertSessionHas('status', 'TaskBuddy AI added 2 tasks to Kay and Leona.');

        $t1 = Task::where('title', 'Update the Ocusafe landing page')->firstOrFail();
        $this->assertSame([$this->kay->id, $ocusafe, '2026-11-10', $campaign->id, $this->kay->id, $this->kay->id, false],
            [$t1->assignee_id, $t1->brand_id, $t1->due_on->format('Y-m-d'), $t1->campaign_id, $t1->created_by, $t1->assigned_by, $t1->hot]);
        $this->assertStringContainsString('Add Z87 badge', $t1->details);
        $t2 = Task::where('title', 'Write launch email 1')->firstOrFail();
        $this->assertSame([$this->leona->id, true, null], [$t2->assignee_id, $t2->hot, $t2->details]);
        $this->assertSame(0, Task::where('title', 'Write launch email 2')->count());

        $run->refresh();
        $this->assertSame(['added', 2], [$run->status, count($run->task_ids)]);
        $this->assertNull(DB::table('taskbuddy_runs')->value('source_text'), 'the source is deleted once the tasks are added');
        $this->assertSame(1, DB::table('activity_logs')->where('description', 'like', '%(TaskBuddy AI)%')->where('description', 'like', '%Leona%')->count());
    }

    public function test_files_are_read_pdfs_and_images_go_to_the_model_and_office_files_as_text(): void
    {
        $this->fakeOpenAi(['title' => 'Plan', 'summary' => 's', 'questions' => [], 'tasks' => [$this->task()]]);

        $pdf = UploadedFile::fake()->createWithContent('brief.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->actingAs($this->kay)->post('/assistant/taskbuddy', ['file' => $pdf])->assertRedirect();
        $part = $this->requests[0]['input'][0]['content'][0];
        $this->assertSame(['input_file', 'brief.pdf'], [$part['type'], $part['filename']]);
        $this->assertSame('data:application/pdf;base64,'.base64_encode("%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"), $part['file_data']);
        $this->assertSame('ready', TaskbuddyRun::latest('id')->first()->status, 'no questions: straight to review');

        $png = UploadedFile::fake()->image('whiteboard.png', 40, 40);
        $this->actingAs($this->kay)->post('/assistant/taskbuddy', ['file' => $png])->assertRedirect();
        $this->assertStringStartsWith('data:image/png;base64,', $this->requests[1]['input'][0]['content'][0]['image_url']);

        // A Word file is read here; its text goes as text.
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<w:document><w:body><w:p><w:r><w:t>Order new &amp; improved samples</w:t></w:r></w:p><w:p><w:r><w:t>Call the printer</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
        $docx = new UploadedFile($path, 'notes.docx', null, null, true);
        $this->actingAs($this->kay)->post('/assistant/taskbuddy', ['file' => $docx, 'text' => 'Also: book the photographer'])->assertRedirect();
        $text = $this->requests[2]['input'][0]['content'][0]['text'];
        $this->assertStringContainsString("Order new & improved samples\nCall the printer", $text);
        $this->assertStringContainsString('Also: book the photographer', $text);

        // Files it can't read are refused with a reason, and nothing is sent.
        $this->actingAs($this->kay)->from('/assistant/taskbuddy')->post('/assistant/taskbuddy', ['file' => UploadedFile::fake()->createWithContent('x.exe', 'MZ')])
            ->assertSessionHasErrors(['file' => 'TaskBuddy can’t read .exe files. Use a PDF, Word, PowerPoint, Excel, image or text file, or paste the text.']);
        $this->actingAs($this->kay)->post('/assistant/taskbuddy', [])->assertSessionHasErrors(['text' => 'Paste some text or choose a file.']);
        $this->assertCount(3, $this->requests);
    }

    public function test_runs_are_private_failures_are_explained_and_can_be_retried(): void
    {
        $this->status = 429;
        $this->fakeOpenAi(['title' => 'Holiday emails', 'summary' => 's', 'questions' => [], 'tasks' => [$this->task(['title' => 'Draft the holiday email'])]]);
        $this->actingAs($this->kay)->post('/assistant/taskbuddy', ['text' => 'Plan the holiday emails']);
        $run = TaskbuddyRun::firstOrFail();
        $this->assertSame('error', $run->fresh()->status);
        $this->actingAs($this->kay)->get(route('taskbuddy.show', $run))->assertOk()->assertSee('OpenAI is rate-limiting or the account is out of credit')->assertSee('Try again');

        $this->actingAs($this->leona)->get(route('taskbuddy.show', $run))->assertNotFound();
        $this->actingAs($this->leona)->getJson(route('taskbuddy.status', $run))->assertNotFound();
        $this->actingAs($this->leona)->post(route('taskbuddy.add', $run), ['tasks' => []])->assertNotFound();

        $this->status = 200;
        $this->actingAs($this->kay)->post(route('taskbuddy.answer', $run), ['retry' => 1])->assertRedirect(route('taskbuddy.show', $run));
        $this->assertSame('ready', $run->fresh()->status);
        $this->actingAs($this->kay)->getJson(route('taskbuddy.status', $run))->assertExactJson(['status' => 'ready']);

        // Sources older than a week are dropped.
        $this->travel(8)->days();
        $this->flushSession();
        $this->withSession(['signed_in_at' => now()->timestamp])->actingAs($this->kay)->get('/assistant/taskbuddy')->assertOk();
        $this->assertNull(DB::table('taskbuddy_runs')->value('source_text'));
    }
}
