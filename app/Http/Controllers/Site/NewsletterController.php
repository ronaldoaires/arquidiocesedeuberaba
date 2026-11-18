<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\NewsletterProfileRequest;
use App\Services\Newsletter\SubscriptionLinks;
use App\Services\Newsletter\Subscriptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class NewsletterController extends Controller
{
    public function __construct(private Subscriptions $subscriptions) {}

    public function subscribe(Request $request): RedirectResponse
    {
        $previous = url()->previous();
        $returnTo = str_starts_with($previous, $request->root().'/') ? $previous : route('site.home');
        $back = redirect()->to(explode('#', $returnTo)[0].'#boletim');
        $validator = Validator::make($request->only('newsletter_email'), [
            'newsletter_email' => ['required', 'string', 'email', 'max:254'],
        ], attributes: ['newsletter_email' => 'e-mail']);

        if ($validator->fails()) {
            return $back->withErrors($validator, 'newsletter')
                ->withInput(['newsletter_email' => is_string($request->input('newsletter_email')) ? $request->input('newsletter_email') : '']);
        }

        $email = $validator->validated()['newsletter_email'];
        try {
            $this->subscriptions->sendAccessLink($email);
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return $back->withErrors(['newsletter_email' => 'Não foi possível enviar o e-mail. Tente reenviar o link.'], 'newsletter')
                ->withInput(['newsletter_email' => $email]);
        }

        return $back->with('newsletter_requested', true)->withInput(['newsletter_email' => $email]);
    }

    public function confirmation(string $key): View
    {
        return view('site.newsletter.profile', [
            'subscriber' => $this->subscriptions->find($key, active: false),
            'confirming' => true,
        ]);
    }

    public function confirm(NewsletterProfileRequest $request, string $key): RedirectResponse
    {
        $subscriber = $this->subscriptions->confirm($key, $request->validated());

        return redirect()->to(SubscriptionLinks::profile($subscriber))
            ->with('newsletter_status', 'Inscrição confirmada! Você receberá os próximos boletins da Arquidiocese.');
    }

    public function profile(string $key): View
    {
        $subscriber = $this->subscriptions->find($key, active: true);

        return view('site.newsletter.profile', [
            'subscriber' => $subscriber,
            'confirming' => false,
            'cancelUrl' => SubscriptionLinks::cancellation($subscriber),
        ]);
    }

    public function saveProfile(NewsletterProfileRequest $request, string $key): RedirectResponse
    {
        $this->subscriptions->updateProfile($key, $request->validated());

        return redirect()->to($request->fullUrl())->with('newsletter_status', 'Seus dados foram atualizados.');
    }

    public function cancellation(string $key): View
    {
        $subscriber = $this->subscriptions->find($key, active: true);

        return view('site.newsletter.cancel', ['subscriber' => $subscriber, 'profileUrl' => SubscriptionLinks::profile($subscriber)]);
    }

    public function unsubscribe(string $key): RedirectResponse
    {
        $this->subscriptions->unsubscribe($key);

        return redirect()->to(route('site.home').'#boletim')->with('newsletter_status', 'Inscrição cancelada. Você deixou de receber o boletim.');
    }
}
