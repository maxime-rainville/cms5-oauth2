<?php

namespace {

    use App\FormSubmittedEvent;
    use ArchiPro\Silverstripe\EventDispatcher\Service\EventService;
    use SilverStripe\CMS\Controllers\ContentController;
    use SilverStripe\Control\HTTPResponse;
    use SilverStripe\Forms\FieldList;
    use SilverStripe\Forms\Form;
    use SilverStripe\Forms\FormAction;
    use SilverStripe\Forms\TextField;
    use SilverStripe\ORM\FieldType\DBHTMLText;

    /**
     * Site tree controller for this installer. Serves the demo contact form.
     *
     * @extends ContentController<Page>
     */
    class PageController extends ContentController
    {
        /**
         * @var array<string, bool>
         */
        private static $allowed_actions = [
            'Form' => true,
            'success' => true,
        ];

        public function Form(): Form
        {
            return Form::create(
                $this,
                'Form',
                FieldList::create([
                    TextField::create('LogMe', 'Message'),
                ]),
                FieldList::create([
                    FormAction::create(
                        'doSubmit',
                        'Submit'
                    )
                ]),
            );
        }

        /**
         * @param array<string, mixed> $data
         */
        public function doSubmit(array $data, Form $form): HTTPResponse
        {
            EventService::singleton()->dispatch(new FormSubmittedEvent($form->getName(), $data));
            return $this->redirect($this->Link('success'));
        }

        public function success(): DBHTMLText
        {
            return $this->renderWith('Page', [
                'Title' => 'Success',
                'Content' => 'Form submitted successfully',
                'Form' => null
            ]);
        }
    }
}
