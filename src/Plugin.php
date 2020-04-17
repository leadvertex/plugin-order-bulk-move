<?php
/**
 * Created for plugin-core
 * Date: 02.03.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Instance\Macros;


use Leadvertex\Plugin\Components\ApiClient\ApiFilterSortPaginate;
use Leadvertex\Plugin\Components\Developer\Developer;
use Leadvertex\Plugin\Components\Form\Form;
use Leadvertex\Plugin\Components\Process\Components\Error;
use Leadvertex\Plugin\Components\Process\Process;
use Leadvertex\Plugin\Components\Purpose\PluginClass;
use Leadvertex\Plugin\Components\Purpose\PluginEntity;
use Leadvertex\Plugin\Components\Purpose\PluginPurpose;
use Leadvertex\Plugin\Components\Translations\Translator;
use Leadvertex\Plugin\Core\Macros\Components\AutocompleteInterface;
use Leadvertex\Plugin\Core\Macros\Helpers\PathHelper;
use Leadvertex\Plugin\Core\Macros\MacrosPlugin;
use Leadvertex\Plugin\Core\Macros\Models\Session;
use Leadvertex\Plugin\Instance\Macros\Forms\PreviewOptionsForm;
use Leadvertex\Plugin\Instance\Macros\Forms\SettingsForm;

class Plugin extends MacrosPlugin
{

    /** @var SettingsForm */
    private $settings;

    /**
     * @inheritDoc
     */
    public static function getLanguages(): array
    {
        return [
            'en_US',
            'ru_RU'
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getDefaultLanguage(): string
    {
        return 'ru_RU';
    }

    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return Translator::get('info', 'PLUGIN_NAME');
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return Translator::get('info', 'PLUGIN_DESCRIPTION') . "\n" . file_get_contents(PathHelper::getRoot()->down('markdown.md'));
    }

    /**
     * @inheritDoc
     */
    public static function getPurpose(): PluginPurpose
    {
        return new PluginPurpose(
            new PluginClass(PluginClass::CLASS_HANDLER),
            new PluginEntity(PluginEntity::ENTITY_ORDER)
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDeveloper(): Developer
    {
        return new Developer(
            'LeadVertex',
            'support@leadvertex.com',
            'https://leadvertex.com'
        );
    }

    /**
     * @inheritDoc
     */
    public function getSettingsForm(): Form
    {
        if (is_null($this->settings)) {
            $this->settings = new SettingsForm();
        }

        return $this->settings;
    }

    /**
     * @inheritDoc
     */
    public function getRunForm(int $number): ?Form
    {
        switch ($number) {
            case 1:
                return PreviewOptionsForm::getInstance();
                break;
            default:
                return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function autocomplete(string $name): ?AutocompleteInterface
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function run(Process $process, ?ApiFilterSortPaginate $fsp)
    {
        $orders = self::getOrdersToMove(Session::current()->getFsp());
        $targetStatus = Session::current()->getSettings()->getData()->get('bulk.status.0');
        $process->initialize(count($orders));
        $process->save();

        if (!$orders['success']) {
            $process->terminate(
                new Error(
                    Translator::get(
                        'process',
                        'ERROR_WHILE_GETTING_ORDERS {errors}',
                        ['errors' => json_encode($orders['errors'])]
                    )
                )
            );
        } else {
            foreach ($orders['data'] as $order) {
                if ($order['status']['id'] == $targetStatus) {
                    $process->skip();
                    $process->save();
                    continue;
                }
                $result = $this->moveOrder($order['id'], $targetStatus);
                if ($result['success']) {
                    $process->handle();
                    $process->save();
                } else {
                    $process->addError(
                        new Error(
                            Translator::get(
                                'process',
                                'ERROR_WHILE_MOVING_ORDER {errors}',
                                ['errors' =>json_encode($result['errors'])]
                            )
                        )
                    );
                    $process->save();
                }
            }
            $process->finish(true);
        }
        $process->save();
    }

    private function moveOrder(int $orderId, int $statusId): array
    {
        $session = Session::current();
        $api = $session->getApiClient();

        $variables['mutation'] = '$id: Id!, $statusId: Id';
        $variables['update'] = 'id: $id, statusId: $statusId';
        $variablesValues = [
            'id' => $orderId,
            'statusId' => $statusId
        ];

        $query = <<<QUERY
mutation({$variables['mutation']}) {
  updateOrder({$variables['update']}){
    id
    status{
      id
    }
  }
}
QUERY;

        $result = $api->query($query, $variablesValues);
        if ($result->hasErrors()) {
            return ['success' => false, 'errors' => $result->getErrors()];
        }
        return ['success' => true, 'data' => $result->getData()['updateOrder']];
    }

    static public function getOrdersToMove(ApiFilterSortPaginate $fsp): array
    {
        $session = Session::current();
        $api = $session->getApiClient();

        $variables['query'] = '$pagination: Pagination!';
        $variables['fetcher'] = 'pagination: $pagination';
        $variablesValues = [
            'pagination' => ['pageSize' => $fsp->getPageSize()]
        ];

        if (!is_null($fsp->getFilters())) {
            $variables['query'] .= ', $filters: OrderFilter';
            $variables['fetcher'] .= ', filters: $filters';
            $variablesValues['filters'] = $fsp->getFilters();
        }

        if (!is_null($fsp->getSort())) {
            $variables['query'] .= ', $sort: OrderSort';
            $variables['fetcher'] .= ', sort: $sort';
            $variablesValues['sort'] = $fsp->getSort();
        }

        $query = <<<QUERY
query ({$variables['query']}){
  company {
    ordersFetcher({$variables['fetcher']}) {
      orders {
        id
        status {
          id
          name
        }
      }
    }
  }
}
QUERY;

        $result = $api->query($query, $variablesValues);
        if ($result->hasErrors()) {
            return ['success' => false, 'errors' => $result->getErrors()];
        }
        return ['success' => true, 'data' => $result->getData()['company']['ordersFetcher']['orders']];
    }

}