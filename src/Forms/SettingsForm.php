<?php


namespace Leadvertex\Plugin\Instance\Macros\Forms;


use Adbar\Dot;
use Leadvertex\Plugin\Components\Form\FieldDefinitions\ListOfEnum\Limit;
use Leadvertex\Plugin\Components\Form\FieldDefinitions\ListOfEnum\Values\StaticValues;
use Leadvertex\Plugin\Components\Form\FieldDefinitions\ListOfEnumDefinition;
use Leadvertex\Plugin\Components\Form\FieldGroup;
use Leadvertex\Plugin\Components\Form\Form;
use Leadvertex\Plugin\Components\Form\FormData;
use Leadvertex\Plugin\Components\Translations\Translator;
use Leadvertex\Plugin\Core\Macros\Models\Session;

class SettingsForm extends Form
{
    public function __construct()
    {
        parent::__construct(
            Translator::get('settings','FORM_TITLE'),
            Translator::get('settings', 'FORM_DESCRIPTION'),
            $this->getFieldsArray(),
            Translator::get('settings', 'FORM_BUTTON')
        );
    }

    private function getFieldsArray(): array
    {
        $staticValidator = function ($values, ListOfEnumDefinition $definition, FormData $data) {
            $limit = $definition->getLimit();

            $errors = [];

            if (!is_null($values) && !is_array($values)) {
                $errors[] = Translator::get('settings', 'STATUS_LIST_VALIDATION_INVALID_ARGUMENT');
                return $errors;
            }

            if ($limit) {

                if ($limit->getMin() && count($values) < $limit->getMin()) {
                    $errors[] = Translator::get('settings', 'STATUS_LIST_VALIDATION_ERROR_MIN {min}', ['min' => $limit->getMin()]);
                }

                if ($limit->getMax() && count($values) > $limit->getMax()) {
                    $errors[] = Translator::get('settings', 'STATUS_LIST_VALIDATION_ERROR_MIN {max}', ['max' => $limit->getMax()]);
                }
            }

            return $errors;
        };

        return  [
            "bulk" => new FieldGroup(
                Translator::get('settings', 'FIELD_GROUP'),
                Translator::get('settings', 'FIELD_GROUP_DESCRIPTION'),
                [
                    'status' => new ListOfEnumDefinition(
                        Translator::get('settings', 'STATUS_FIELD'),
                        Translator::get('settings', 'STATUS_FIELD_DESCRIPTION'),
                        $staticValidator,
                        new StaticValues($this->getStatuses()),
                        new Limit(1, 1)
                    ),
                ]
            )
        ];
    }

    private function getStatuses(): array
    {
        $api = Session::current()->getApiClient();

        $query = <<<QUERY
{
  company{
    statusesFetcher(filters:{archived:false}){
      statuses{
        id
        name
        group
      }
    }
  }
}    
QUERY;

        $queryResult = $api->query($query, []);

        $queryResult = (new Dot($queryResult->getData()))->get('company.statusesFetcher.statuses');
        $statuses = [];

        array_walk($queryResult, function ($status) use (&$statuses) {
            $statuses[$status['id']] = [
                'title' => $status['name'],
                'group' => $status['group']
            ];
        });

        return $statuses;
    }

}