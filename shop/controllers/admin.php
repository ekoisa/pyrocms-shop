<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Admin extends Admin_Controller {

    private $cat_validation_rules = array(
        array(
            'field' => 'title',
            'label' => 'lang:shop.categories_title_label',
            'rules' => 'trim|required|max_length[20]|callback_check_title'
        ),
    );

    private $item_validation_rules = array(
        array(
            'field' => 'title',
            'label' =>'lang:shop.item_title_label',
            'rules' => 'trim|required|max_length[20]|callback_check_title'
        ),
        array(
            'field' => 'category',
            'label' => 'lang:shop.item_category_label',
            'rules' => 'trim'
        ),
        array(
            'field' => 'price',
            'label' => 'lang:shop.item_price_label',
            'rules' => 'trim|required|numeric'
        ),
        array(
            'field' => 'gallery',
            'label' => 'lang:shop.item_gallery_label',
            'rules' => 'trim'
        ),
        array(
            'field' => 'status',
            'label' => 'lang:shop.item_status_label',
            'rules' => 'trim|alpha'
        ),
        array(
            'field' => 'description',
            'label' => 'lang:shop.item_description_label',
            'rules' => 'trim|max_length[255]'
        )
    );


    public function __construct()
    {
        parent::__construct();
        $this->load->model('shop_cat_m');
        $this->load->model('shop_items_m');
        $this->load->model('cart_m');
        $this->load->library('form_validation');
        $this->load->helper('html');
        $this->lang->load('shop');
	$this->template->set_partial('shortcuts', 'admin/partials/shortcuts');

        $this->data->categories = array();
	if ($categories = $this->shop_cat_m->order_by('name')->get_all())
	{
		foreach ($categories->result() as $category)
		{
			$this->data->categories[$category->id] = $category->name;
		}
	}
    }

    

    public function index()
    {
        $all_cat = $this->shop_cat_m->get_all();

        $this->template
                ->title($this->module_details['name'])
                ->set('all_cat', $all_cat)
                ->build('admin/index');
    }



    public function create_item()
    {
        // Javascript for dynamic textboxes
        $html_to_paste = '<li><label for=value>' .$this->lang->line("shop.item_option_value_label").
                         ' #" + counter + "</label><input class=text type=text name=value" + counter + "></li>';

        $shop_js = '<script type="text/javascript">
            jQuery(function(){
                var counter = 2;
                jQuery("#add_value").click(function(){
                    counter++;
                    jQuery(".add_value").before("' .$html_to_paste. '");
                    return false;
                });
             });
                </script>';

        $this->load->model('galleries/galleries_m');

        // All galleries names to array
        $galleries = $this->galleries_m->get_all();
        $gal = array();
        foreach ($galleries as $gallery) {
            $gal[$gallery->id] = $gallery->title;
        }
        
        $this->form_validation->set_rules($this->item_validation_rules);

        if($this->form_validation->run()) {
            if ($this->shop_items_m->create($this->input->post())) {
                $this->session->set_flashdata('success', sprintf( lang('shop.item_add_success'), $this->input->post('title')) );
                redirect('admin/shop/list_items');
            }
            // if not
        }

        // Loop through each validation rule
        foreach($this->item_validation_rules as $rule)
        {
                $post->{$rule['field']} = set_value($rule['field']);
        }

        // Render the view
        $this->data->post =& $post;
        $this->template
                        ->title($this->module_details['name'], lang('shop.item_create_title'))
                        ->append_metadata($this->load->view('fragments/wysiwyg', $this->data, TRUE))
                        ->append_metadata(css('shop-style.css', 'shop'))
                        ->append_metadata($shop_js)
                        ->set('galleries', $gal)
                        ->build('admin/create_item', $this->data);
    }





    public function list_items()
    {
        $all_items = $this->shop_items_m->get_all();
        $data['all_items'] = $all_items;
        $this->template
                        ->title($this->module_details['name'], lang('shop.item_list_title'))
                        ->set('categories', $this->data->categories)
                        ->set_partial('filters', 'admin/partials/filters')
                        ->build('admin/list_items', $data);
    }


    public function delete_item($id=0)
    {
        $id_array = (!empty($id)) ? array($id) : $this->input->post('action_to');

        if (!empty($id_array)) {
            $deleted = 0;
            $to_delete = 0;
            
            foreach ($id_array as $id) {
                if ($this->shop_items_m->delete($id)) {
                    $deleted++;
                }
                else {
                    $this->session->set_flashdata('error', sprintf($this->lang->line('shop.item_mass_delete_error'), $id));
                }
                $to_delete++;
            }
            if ($deleted > 0) {
                $this->session->set_flashdata('success', sprintf($this->lang->line('shop.item_mass_delete_success'), $deleted, $to_delete));
            }
        }
        else {
            $this->session->set_flashdata('error', $this->lang->line('shop.item_no_select_error'));
        }

        redirect('admin/shop/list_items');
    }


    public function edit_item($id=0)
    {
        $item = $this->shop_items_m->get($id);
        $item->title = $item->name;
        $item->status = ($item->active == 0) ? 'Draft' : 'Live';

            $item or redirect('admin/shop/list_items');

            $this->load->model('galleries/galleries_m');
            $galleries = $this->galleries_m->get_all();

            // Save all galleries titles in array
            foreach($galleries as $gallery) {
                $gal[$gallery->id] = $gallery->title;
            }

            $this->form_validation->set_rules($this->item_validation_rules);

            if ($this->form_validation->run()) {
                $this->shop_items_m->edit($id, $this->input->post());
            }


            // Loop through each validation rule
            foreach($this->item_validation_rules as $rule)
            {
                if ($this->input->post($rule['field']) !== false) {
                    $item->{$rule['field']} = set_value($rule['field']);
                }
            }
            

            // Render the view
            $this->data->post =& $item;
            $this->template->title($this->module_details['name'], lang('shop.item_edit_title'))
                                            ->append_metadata($this->load->view('fragments/wysiwyg', $this->data, TRUE))
                                            ->set('galleries', $gal)
                                            ->build('admin/edit_item', $this->data);
    }


    public function create_category()
    {
        $this->form_validation->set_rules($this->cat_validation_rules);
        if ($this->form_validation->run()) {
            $post = $this->input->post();
            if ($this->shop_cat_m->create($post['title'])) {
                $this->session->set_flashdata('success', sprintf( lang('shop.cat_add_success'), $this->input->post('title')) );
                redirect('admin/shop');
            }
            $this->session->set_flashdata(array('error'=> lang('cat_add_error')));
        }

        // Loop through each validation rule
        foreach($this->cat_validation_rules as $rule)
        {
                $category->{$rule['field']} = set_value($rule['field']);
        }

        // Render the view
        $this->data->category =& $category;
        $this->template->title($this->module_details['name'], lang('shop.cat_create_title'))
                                        ->build('admin/create_category', $this->data);
    }




    public function delete_category($id=0)
    {
        $id_array = (!empty($id)) ? array($id) : $this->input->post('action_to');

        // Delete multiple
        if (!empty($id_array)) {
            
                $deleted = 0;
                $to_delete = 0;
                
                foreach ($id_array as $id) {
                        if($this->shop_cat_m->delete($id)) {
                                $deleted++;
                        }
                        else {
                                $this->session->set_flashdata('error', sprintf($this->lang->line('shop.cat_mass_delete_error'), $id));
                        }
                        $to_delete++;
                }

                if( $deleted > 0 ) {
                        $this->session->set_flashdata('success', sprintf($this->lang->line('shop.cat_mass_delete_success'), $deleted, $to_delete));
                }
        }
        else {
                $this->session->set_flashdata('error', $this->lang->line('shop.cat_no_select_error'));
        }

        redirect('admin/shop/index');
    }




    public function edit_category($id=0)
    {
        $category = $this->shop_cat_m->get($id);

		// ID specified?
		$category or redirect('admin/shop/index');

                $this->form_validation->set_rules($this->cat_validation_rules);

		// Validate the results
		if ($this->form_validation->run())
		{
			$this->shop_cat_m->edit($_POST['title'], $id)
				? $this->session->set_flashdata('success', sprintf( lang('shop.cat_edit_success'), $this->input->post('title')) )
				: $this->session->set_flashdata(array('error'=> lang('shop.cat_edit_error')));

			redirect('admin/shop/index');
		}

		// Loop through each rule
		foreach($this->cat_validation_rules as $rule)
		{
			if($this->input->post($rule['field']) !== FALSE)
			{
				$category->{$rule['field']} = $this->input->post($rule['field']);
			}
		}

		// Render the view
		$this->data->category =& $category;
		$this->template->title($this->module_details['name'], sprintf(lang('shop.cat_edit_title'), $category->name))
						->build('admin/edit_category', $this->data);
    }




    public function list_orders()
    {
        $orders = $this->cart_m->get_all();
        $this->data->orders = $orders;
        // Render the view
        $this->template
                        ->title($this->module_details['name'])
                        ->set('categories', $this->data->categories)
                        ->set_partial('filters', 'admin/partials/filterorders')
                        ->build('admin/list_orders', $this->data);
    }




    public function view_order($id=0)
    {   
        $cart = $this->cart_m->get($id);
        $items = $this->cart_m->get_items($id);
        $customer_id = $cart->customer;

        $this->data->cart = $cart;
        $this->data->items = $items;

        // Render the view
        $this->template
                        ->title($this->module_details['name'])
                        ->build('admin/view_order', $this->data);
    }





    /**
     * Callback method that checks the title of the category
     * @access public
     * @param string title The title to check
     * @return bool
     */
    public function _check_title($title = '')
    {
            if ($this->shop_cat_m->check_name($title))
            {
                    $this->form_validation->set_message('_check_title', sprintf($this->lang->line('cat_already_exist_error'), $title));
                    return FALSE;
            }

            return TRUE;
    }
}