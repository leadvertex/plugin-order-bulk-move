<?php


namespace Leadvertex\Plugin\Instance\Macros\Forms;


use Leadvertex\Plugin\Components\Form\FieldGroup;
use Leadvertex\Plugin\Components\Form\Form;
use Leadvertex\Plugin\Components\Translations\Translator;
use Leadvertex\Plugin\Core\Macros\Models\Session;
use Leadvertex\Plugin\Instance\Macros\Components\OptionsSingletonTrait;
use Leadvertex\Plugin\Instance\Macros\Plugin;

class PreviewOptionsForm extends Form
{

    use OptionsSingletonTrait;

    private function __construct()
    {
        $queryResult = Plugin::getOrdersToMove(Session::current()->getFsp());
        if ($queryResult['success']) {
            $groupDescription = Translator::get(
                'preview_options',
                'ORDERS_DESCRIPTION {ordersCount} {ordersTable}',
                [
                    'ordersCount' => count($queryResult['data']),
                    'ordersTable' => $this->generateMarkdownTableForOrdersIds($queryResult['data'])
                ]
            );
        } else {
            $groupDescription = Translator::get(
                'preview_options',
                'QUERY_ERRORS_DESCRIPTION {errors}',
                ['errors' => json_encode($queryResult['errors'])]
            );
        }

        parent::__construct(
            Translator::get('preview_options', 'OPTIONS_TITLE'),
            Translator::get('preview_options', 'OPTIONS_DESCRIPTION'),
            [
                'preview_options' => new FieldGroup(
                    Translator::get('preview_options', 'GROUP_1'),
                    $groupDescription,
                    []
                )
            ],
            Translator::get(
                'preview_options',
                'FORM_BUTTON'
            )
        );
    }

    private function generateMarkdownTableForOrdersIds(array $orders): string
    {
        $tableContent = '';
        $tableHeader = <<<MARKDOWN
|Orders ids|Status name|
|---|---|

MARKDOWN;
        foreach ($orders as $order) {
            $tableContent .= <<<MARKDOWN
|{$order['id']}|{$order['status']['name']}|

MARKDOWN;
        }
        $tableContent = substr($tableContent, 0, -2);
        return $tableHeader . $tableContent;
    }
}