<?php

namespace App\Filament\Resources\NewsletterCampaigns;

use App\Filament\Clusters\Newsletter;
use App\Mail\NewsletterCampaignMail;
use App\Models\NewsletterCampaign;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class CampaignActions
{
    public static function preview(): Action
    {
        return Action::make('preview')->label('Pré-visualizar')->modalHeading('Prévia do e-mail')
            ->modalSubmitAction(false)->modalCancelActionLabel('Fechar')->modalWidth('3xl')
            ->modalContent(function (NewsletterCampaign $record): View {
                $message = new NewsletterCampaignMail($record);
                try {
                    $html = $message->render();

                    return view('filament.newsletter.preview', ['html' => $html, 'campaign' => $record]);
                } catch (ValidationException $exception) {
                    return view('filament.newsletter.preview', ['error' => $exception->getMessage()]);
                }
            });
    }

    public static function sendTest(): Action
    {
        return Action::make('sendTest')->label('Enviar teste para mim')->requiresConfirmation()
            ->modalHeading('Enviar e-mail de teste')->modalDescription(fn (): string => 'Enviar esta versão salva para '.auth()->user()->email.'?')
            ->modalSubmitActionLabel('Enviar teste')->action(function (NewsletterCampaign $record): void {
                abort_unless(Newsletter::canAccess(), 403);
                $email = auth()->user()->email;
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Notification::make()->danger()->title('Cadastre um e-mail válido em seu perfil para receber o teste.')->send();

                    return;
                }
                try {
                    Mail::to($email)->send(new NewsletterCampaignMail($record));
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                } catch (TransportExceptionInterface $exception) {
                    report($exception);
                    Notification::make()->danger()->title('O serviço de e-mail não aceitou o teste. Tente novamente.')->send();

                    return;
                }

                Notification::make()->success()->title(config('mail.default') === 'log'
                    ? 'E-mail de teste registrado no log local.'
                    : 'E-mail de teste enviado para '.$email.'.')->send();
            });
    }
}
