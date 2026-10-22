<?php
declare(strict_types=1);
namespace App\Support;

/** Albanian labels and badge tones for every status code shown in the interface. */
final class Labels
{
    /** @var array<string,array<string,array{0:string,1:string}>> domain => code => [label, tone] */
    private const MAP = [
        'status' => ['ACTIVE'=>['Aktiv','success'],'DEACTIVATED'=>['Joaktiv','danger'],'NEEDS_REVIEW'=>['Për shqyrtim','warning']],
        'classification' => ['AUTO'=>['Automatik','success'],'MANUAL'=>['Manual','info'],'UNCLASSIFIED'=>['Pa klasifikim','neutral'],'AMBIGUOUS'=>['I paqartë','danger'],'NO_RELATION'=>['Pa relacion','warning']],
        'invoice' => ['ISSUED'=>['E papaguar','warning'],'PARTIALLY_PAID'=>['Pjesërisht e paguar','info'],'PAID'=>['E paguar','success'],'CANCELLED'=>['E anuluar','neutral'],'OVERDUE'=>['Me afat të kaluar','danger']],
        'invoice_event' => ['CREATED'=>['Krijuar','info'],'EDITED'=>['Edituar','warning'],'CANCELLED'=>['Anuluar','danger'],'RESTORED'=>['Rikthyer','info'],'PAYMENT_RECORDED'=>['Pagesë e regjistruar','success'],'PAYMENT_REVERSED'=>['Pagesë e stornuar','danger'],'EMAILED'=>['Dërguar me email','info']],
        'appeal' => ['SUBMITTED'=>['Dorëzuar','warning'],'ACCEPTED'=>['Pranuar','info'],'COORDINATOR'=>['Te koordinatori','info'],'COMMISSION'=>['Te komisioni','info'],'UNDER_REVIEW'=>['Në shqyrtim','info'],'DECIDED'=>['Vendosur','success'],'ANSWERED'=>['Përgjigjur','success'],'REJECTED'=>['Refuzuar','danger']],
        'appeal_action' => ['ACCEPTED'=>['Prano ankesën','info'],'REJECTED'=>['Refuzo ankesën','danger'],'COORDINATOR'=>['Merr si koordinator','info'],'COMMISSION'=>['Dërgo te komisioni','info'],'UNDER_REVIEW'=>['Fillo shqyrtimin','info'],'DECIDED'=>['Regjistro vendimin','success'],'ANSWERED'=>['Shëno si të përgjigjur','success']],
        'decision' => ['APPROVED'=>['Aprovohet','success'],'PARTIALLY_APPROVED'=>['Aprovohet pjesërisht','info'],'REJECTED'=>['Refuzohet','danger']],
        'access' => ['PENDING'=>['Në pritje','warning'],'APPROVED'=>['Aprovuar','success'],'REJECTED'=>['Refuzuar','danger']],
        'permit' => ['ACTIVE'=>['Aktive','success'],'EXPIRED'=>['Skaduar','neutral'],'REVOKED'=>['Revokuar','danger']],
        'import' => ['QUEUED'=>['Në radhë','neutral'],'PROCESSING'=>['Në përpunim','info'],'COMPLETED'=>['Përfunduar','success'],'FAILED'=>['Dështuar','danger']],
        'import_type' => ['ALL'=>['Regjistri i plotë','neutral'],'WOMEN'=>['Biznese grua/vajzë','neutral'],'CLOSED'=>['Biznese të shuara','neutral'],'CONTACTS'=>['Kontaktet','neutral'],'UNITS'=>['Njësitë / degët','neutral']],
        'job' => ['QUEUED'=>['Në radhë','neutral'],'PROCESSING'=>['Në përpunim','info'],'COMPLETED'=>['Përfunduar','success'],'COMPLETED_WITH_ERRORS'=>['Përfunduar me gabime','warning'],'FAILED'=>['Dështuar','danger']],
        'artifact' => ['PENDING'=>['Në pritje','neutral'],'BUILDING'=>['Po krijohet','info'],'READY'=>['Gati','success'],'FAILED'=>['Dështuar','danger']],
        'payment_row' => ['APPLIED'=>['E aplikuar','success'],'DUPLICATE'=>['Dublikatë','neutral'],'UNMATCHED'=>['Pa faturë','danger'],'OVERPAYMENT'=>['Tejpagesë','warning'],'CANCELLED_INVOICE'=>['Faturë e anuluar','warning'],'INVALID'=>['E pavlefshme','danger']],
        'message' => ['QUEUED'=>['Në radhë','neutral'],'SENT'=>['Dërguar','success'],'FAILED'=>['Dështuar','danger']],
        'message_type' => ['INVOICE'=>['Faturë','neutral'],'REGISTRATION_INVITE'=>['Ftesë regjistrimi','neutral']],
        'role' => ['ADMIN'=>['Administrator','info'],'OFFICIAL'=>['Zyrtar','info'],'READ_ONLY'=>['Vetëm lexim','neutral'],'BUSINESS'=>['Biznes','neutral']],
        'active' => ['1'=>['Aktiv','success'],'0'=>['Joaktiv','neutral']],
        'yes_no' => ['1'=>['Po','info'],'0'=>['Jo','neutral']],
    ];

    public static function label(string $domain, string $code): string
    {
        return self::MAP[$domain][$code][0] ?? ($code === '' ? '—' : $code);
    }

    public static function tone(string $domain, string $code): string
    {
        return self::MAP[$domain][$code][1] ?? 'neutral';
    }

    /** @return array<string,string> code => label */
    public static function options(string $domain): array
    {
        return array_map(static fn(array $entry): string => $entry[0], self::MAP[$domain] ?? []);
    }
}
