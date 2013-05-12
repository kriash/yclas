<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Ad extends Controller {
	

	/**
	 * Publis all adver.-s without filter
	 */
	public function action_listing()
	{ 
		Breadcrumbs::add(Breadcrumb::factory()->set_title(__('Home'))->set_url(Route::url('default')));
		
        /**
         * we get the id of category and location from controller 
         */
        
        if (Controller::$category!==NULL)
        {
            if (Controller::$category->loaded())
            {
        	    $cat_filter = Controller::$category->id_category;
            	Breadcrumbs::add(Breadcrumb::factory()->set_title(Controller::$category->name)->set_url(Route::url('list', array('category'=>Controller::$category->seoname))));	
           	}
           	else
            	$cat_filter = NULL;       
        }
        else
        	$cat_filter = NULL;

        if (Controller::$location!==NULL)
        {
            if (Controller::$location->loaded())
            {
            	$loc_filter = Controller::$location->id_location;
            	Breadcrumbs::add(Breadcrumb::factory()->set_title(Controller::$location->name)->set_url(Route::url('list', array('location'=>Controller::$location->seoname))));
            }
            else
            	$loc_filter = Controller::$location;       
        }
        else
        	$loc_filter = Controller::$location;
		
        // list by category / location
   		if(Controller::$category !== NULL && Controller::$location !== NULL) // cat(specific) + loc(specific)
   			$data = $this->list_logic($cat_filter, $loc_filter);
   		else if ($this->request->param('category') == 'all' && Controller::$location !== NULL) // for all categories with a specific location
   			$data = $this->list_logic( 'all' ,  $loc_filter);
   		else if (is_numeric($cat_filter) && $this->request->param('location') == NULL) // specific category
   			$data = $this->list_logic( $cat_filter );
   		else if ($this->request->param('category') == 'all' && $this->request->param('location') == NULL) // all ads 
   			$data = $this->list_logic('all');	
   		else if ((Controller::$category == NULL && Controller::$location == NULL) || 
   				 (Controller::$category != NULL && Controller::$location == NULL) || 
   				 (Controller::$category == NULL && Controller::$location != NULL)) // one of choices is non existing 
   			{
   			// if sort_by_cat and sort_by_loc == NULL redirect to search
   			$this->request->redirect(Route::url('search'));
   			}
   		else
   			$data = $this->list_logic();
   			
   		
		$this->template->bind('content', $content);
		$this->template->content = View::factory('pages/ad/listing',$data);
       
        
 	}

 	/**
 	 * [list_logic Returnes arrays with necessary data to publis advert.-s]
 	 * @param  [string] $sort_by_cat [name of category] 
 	 * @param  [string] $sort_by_loc [name of location]
  	 * @return [array] [$ads, $pagination, $user, $image_path]
 	 */
	public function list_logic($sort_by_cat = NULL, $sort_by_loc = NULL)
	{

		//user recognition 
		$user = (Auth::instance()->get_user() == NULL) ? NULL : Auth::instance()->get_user();

		$ads = new Model_Ad();
		

		$cat = new Model_Category($sort_by_cat);
		if($sort_by_cat == 'all') $categ = $cat->find_all(); else if ($sort_by_cat == NULL) $res_count = 0; else $categ = $cat->id_category;	
		
		$loc = new Model_Location($sort_by_loc);
		if($sort_by_loc == NULL) $locat = $loc->find_all(); else $locat = $loc->id_location;
		

		// if is sorted by category , or category + location (filter query)
		if(is_numeric($locat))
		{
			if(!is_numeric($categ))
				$ads->where('status', '=', Model_Ad::STATUS_PUBLISHED)
					->and_where('id_location', '=', $locat);	
			else
				$ads->where('status', '=', Model_Ad::STATUS_PUBLISHED)
					->and_where('id_category', '=', $categ)
					->and_where('id_location', '=', $locat);
		} 
		else if(is_numeric($categ))
		{
			if(is_numeric($locat))
				$ads->where('status', '=', Model_Ad::STATUS_PUBLISHED)
					->and_where('id_category', '=', $categ)
					->and_where('id_location', '=', $locat);
			else
				$ads->where('status', '=', Model_Ad::STATUS_PUBLISHED)
					->and_where('id_category', '=', $categ);
			
		}
		else
		{
			$ads->where('status', '=', Model_Ad::STATUS_PUBLISHED);
		}


        //if ad have passed expiration time dont show 
        if(core::config('advertisement.expire_date') > 0)
        {
            $ads->where(DB::expr('DATE_ADD( published, INTERVAL '.core::config('advertisement.expire_date').' DAY)'), '>', DB::expr('NOW()'));
        }
        

		$res_count = $ads->count_all();
		// check if there are some advet.-s
		if ($res_count > 0)
		{
   
       		// pagination module
       		$pagination = Pagination::factory(array(
                    'view'           	=> 'pagination',
                    'total_items'    	=> $res_count,
                    'items_per_page' 	=> core::config('general.advertisements_per_page'),
     	    ))->route_params(array(
                    'controller' 		=> $this->request->controller(),
                    'action'      		=> $this->request->action(),
                    'category' 			=> $cat->seoname,
                    'location'			=> $loc->seoname, 
    	    ));
    	   
     	    Breadcrumbs::add(Breadcrumb::factory()->set_title(__("Page ").$pagination->current_page));

     	    //we sort all ads with few parameters
       		$ads = $ads->order_by('published','desc')
		        	            ->limit($pagination->items_per_page)
		        	            ->offset($pagination->offset)
		        	            ->find_all();
		}
		else
		{
			// array of categories sorted for view
			return array('ads'			=> NULL,
						 'pagination'	=> NULL, 
						 'user'			=> NULL, 
						 'thumb' 		=> NULL,
						 'cat'			=> NULL,
						 'loc'			=> NULL,);
		}
		
		// array of categories sorted for view
		return array('ads'			=> $ads,
					 'pagination'	=> $pagination, 
					 'user'			=> $user, 
					 'cat'			=> $categ,
					 'loc'			=> $locat,);
	}

	/**
	 * 
	 * Display single advert. 
	 * @throws HTTP_Exception_404
	 * 
	 */
	public function action_view()
	{
		
		$seotitle = $this->request->param('seotitle',NULL);
		$category = $this->request->param('category');
		
		if ($seotitle!==NULL)
		{
			$ad = new Model_Ad();
			$ad->where('seotitle','=', $seotitle)
				 ->limit(1)->find();
			
			$cat = new Model_Category($ad->id_category);
			$loc = new Model_Location($ad->id_location);

			if ($ad->loaded())
			{
				Breadcrumbs::add(Breadcrumb::factory()->set_title(__('Home'))->set_url(Route::url('default')));
				if (Controller::$category!==NULL)
		       	{
		           	if (Controller::$category->loaded())
		               	Breadcrumbs::add(Breadcrumb::factory()->set_title(Controller::$category->name)->set_url(Route::url('list', array('category'=>Controller::$category->seoname))));
		        
		       }
				Breadcrumbs::add(Breadcrumb::factory()->set_title($ad->title));   	

				// seo title and descr
				
				$parent_categ = new Model_Category($cat->id_category_parent);
                $parent_locat = new Model_Location($loc->id_location_parent);
                if($parent_categ->loaded() AND ($cat->id_category_parent != 1))
                	$parent_categ_concat = '-'.$parent_categ->seoname;
                else
                	$parent_categ_concat = NULL;
                if($parent_locat->loaded() AND ($loc->id_location_parent != 1))
                	$parent_locat_concat = '-'.$parent_locat->seoname;
                else 
                	$parent_locat_concat = NULL;

               
              
           		$this->template->title = $ad->title.$parent_categ_concat.'-'.$cat->seoname.$parent_locat_concat.'-'.$loc->seoname ;
                $this->template->meta_description = text::removebbcode($ad->description);

				$permission = TRUE; //permission to add hit to advert and give access rights. 
				if(!Auth::instance()->logged_in() || 
					(Auth::instance()->get_user()->id_user != $ad->id_user && Auth::instance()->get_user()->id_role != Model_Role::ROLE_ADMIN) || 
					Auth::instance()->get_user()->id_role != Model_Role::ROLE_ADMIN)
				{	
					if(!Auth::instance()->logged_in())
						$visitor_id = NULL;
					else
						$visitor_id = Auth::instance()->get_user()->id_user;
					$do_hit = $ad->count_ad_hit($ad->id_ad, $visitor_id, ip2long(Request::$client_ip)); // hits counter
					
					$permission = FALSE;
					$user = NULL;
					
				} else $user = Auth::instance()->get_user()->id_user;
				//count how many matches are found 
		        $hits = new Model_Visit();
		        $hits = $hits->where('id_ad','=', $ad->id_ad)->count_all();

				$captcha_show = core::config('advertisement.captcha');	

				$this->template->bind('content', $content);
				$this->template->content = View::factory('pages/ad/single',array('ad'				=>$ad,
																				   'permission'		=>$permission, 
																				   'hits'			=>$hits, 
																				   'captcha_show'	=>$captcha_show,
																				   'user'			=>$user));

			}
			//not found in DB
			else
			{
				//throw 404
				throw new HTTP_Exception_404();
			}
			
		}
		else//this will never happen
		{
			//throw 404
			throw new HTTP_Exception_404();
		}
	}
	
	
	/**
	 * [image_path Get directory path of specific advert.]
	 * @param  [array] $data [all values of one advert.]
	 * @return [array]       [array of dir. path where images of advert. are ]
	 */
	public function image_path($data)
	{
		$obj_ad = new Model_Ad();
		$directory = $obj_ad->gen_img_path($data->id_ad, $data->created);

		$path = array();
		if(is_dir($directory))
		{	
			$filename = array_diff(scandir($directory, 1), array('..','.')); //return all file names , and store in array 

			foreach ($filename as $filename) {
				array_push($path, $directory.$filename);		
			}
		}
		else
		{ 	
			return FALSE ;
		}

		return $path;
	}

	/**
	 * [action_to_top] [pay to go on top, and make order]
	 *
	 * @TODO if paymant is corrent and done update order table(status, pay_date), and put it to top (change published date)
	 */
	public function action_to_top()
	{
		$payer_id 		= Auth::instance()->get_user()->id_user; 
		$id_product 	= Paypal::to_top;
		$description 	= 'to_top';
		// update orders table
		// fields
		$ad = new Model_Ad($this->request->param('id'));
		
		//case when payment is set to 0, it gets published without payment
		if(core::config('payment.pay_to_go_on_top') == FALSE)
		{
			$ad->status = 1;
			$ad->published = Date::unix2mysql(time());

			try {
				$ad->save();
				$this->request->redirect(Route::url('list')); 

			} catch (Exception $e) {
				throw new HTTP_Exception_500($e->getMessage());
			}
		}
		
		$ord_data = array('id_user' 	=> $payer_id,
						  'id_ad' 		=> $ad->id_ad,
						  'id_product' 	=> $id_product,
						  'paymethod' 	=> 'paypal', // @TODO - to strict
						  'currency' 	=> core::config('payment.paypal_currency'),
						  'amount' 		=> core::config('payment.pay_to_go_on_top'),
						  'description'	=> $description);

		$order_id = new Model_Order(); // create order , and returns order id
		$order_id = $order_id->set_new_order($ord_data);
	
		
		// redirect to payment
		$this->request->redirect(Route::url('default', array('controller' =>'payment_paypal','action'=>'form' ,'id' => $order_id)));

	}
	
	/**
	 * [action_to_featured] [pay to go in featured]
	 *
	 * @TODO - when paypal returns token, update
	 */
	public function action_to_featured()
	{
		$payer_id 		= Auth::instance()->get_user()->id_user; 
		$id_product 	= Paypal::to_featured;
		$description 	= 'to_featured';

		// update orders table
		// fields
		$ad = new Model_Ad($this->request->param('id'));
	
		//case when payment is set to 0, it gets published without payment
		if(core::config('payment.pay_to_go_on_feature') == FALSE)
		{
			$ad->status = 1;
			$ad->featured = Date::unix2mysql(time() + (core::config('advertisement.featured_timer') * 24 * 60 * 60));

			try {
				$ad->save();
				$this->request->redirect(Route::url('list')); 

			} catch (Exception $e) {
				throw new HTTP_Exception_500($e->getMessage());
			}
		}

		$ord_data = array('id_user' 	=> $payer_id,
						  'id_ad' 		=> $ad->id_ad,
						  'id_product' 	=> $id_product,
						  'paymethod' 	=> 'paypal', // @TODO - to strict
						  'currency' 	=> core::config('payment.paypal_currency'),
						  'amount' 		=> core::config('payment.pay_to_go_on_feature'),
						  'description'	=> $description);
		
		$order_id = new Model_Order(); // create order , and returns order id
		$order_id = $order_id->set_new_order($ord_data);
		// redirect to payment
		$this->request->redirect(Route::url('default', array('controller' =>'payment_paypal','action'=>'form' ,'id' => $order_id)));
	}
	
	public function action_confirm_post()
	{
		$advert_id = $this->request->param('id');

		$advert = new Model_Ad($advert_id);

		if($advert->loaded())
		{
			if(core::config('general.moderation') == 3)
			{

				$advert->status = 1; // status active
				$advert->published = Date::unix2mysql(time());

				try 
				{
					$advert->save();
					Alert::set(Alert::INFO, __('Your advertisement is successfully activated! Thank you!'));
					$this->request->redirect(Route::url('ad', array('category'=>$advert->id_category, 'seotitle'=>$advert->seotitle)));	
				} 
				catch (Exception $e) 
				{
					throw new HTTP_Exception_500($e->getMessage());
				}
			}
		}
	}

	public function action_advanced_search()
	{
		//template header
		$this->template->title           	= __('Advansed Search');
		$this->template->meta_description	= __('Advansed Search');

		//breadcrumbs
		Breadcrumbs::add(Breadcrumb::factory()->set_title(__('Home'))->set_url(Route::url('default')));
		

		$cat_obj = new Model_Category();
		$loc_obj = new Model_Location();

		// filter home categ and location
		$cat = $cat_obj->where('id_category','!=',1)->order_by('order','asc')->cached()->find_all();
		$loc = $loc_obj->where('id_location','!=',1)->order_by('order','asc')->cached()->find_all();

		$user = (Auth::instance()->get_user() == NULL) ? NULL : Auth::instance()->get_user();

		if($this->request->query()) // after query has detected
		{			
        	// variables 
        	$search_advert 	= $this->request->query('advert');
        	$search_cat 	= $this->request->query('category');
        	$search_loc 	= $this->request->query('location');
        	
        	// filter by each variable
        	$adverts = new Model_Ad();
        	$ads = $adverts->where('status', '=', Model_Ad::STATUS_PUBLISHED);

        	//if ad have passed expiration time dont show 
	        if(core::config('advertisement.expire_date') > 0)
	        {
	            $ads->where(DB::expr('DATE_ADD( published, INTERVAL '.core::config('advertisement.expire_date').' DAY)'), '>', DB::expr('NOW()'));
	        }

	        if(!empty($search_advert) OR $this->request->query('search'))
	        {	
	        	// if user is using search from header
	        	if($this->request->query('search'))
	        		$search_advert = $this->request->query('search');

	        	$ads = $ads->where('title', 'like', '%'.$search_advert.'%');
	        }
	        	
	          	
	        if(!empty($search_cat))
	        {  
	            $cat_obj->where('seoname', '=', $search_cat)
	                                 ->limit(1)
	                                 ->find();

	            $ads = $ads->where('id_category', '=', $cat_obj->id_category);
	            
	        }

	        if(!empty($search_loc))
	        {
	            $loc_obj->where('seoname', '=', $search_loc)
	                                 ->limit(1)
	                                 ->find();
	           
	            $ads = $ads->where('id_location', '=', $loc_obj->id_location);
	        }
	        // count them for pagination
			$res_count = $adverts->count_all();

			if($res_count>0)
			{
				
	           		if ($cat_obj->loaded())
	               		Breadcrumbs::add(Breadcrumb::factory()->set_title($cat_obj->name)->set_url(Route::url('list', array('category'=>$cat_obj->seoname))));
	               	if ($loc_obj->loaded())
	               		Breadcrumbs::add(Breadcrumb::factory()->set_title($loc_obj->name)->set_url(Route::url('list', array('location'=>$loc_obj->seoname))));
	        
				$pagination = Pagination::factory(array(
		                    'view'           	=> 'pagination',
		                    'total_items'      	=> $res_count,
		                    'items_per_page' 	=> core::config('general.advertisements_per_page'),
		        ))->route_params(array(
		                    'controller' 		=> $this->request->controller(),
		                    'action'     	 	=> $this->request->action(),
		                    'category'			=> $cat_obj->seoname,
		        ));

		        Breadcrumbs::add(Breadcrumb::factory()->set_title(__("Page ").$pagination->offset));
				
				$ads = $adverts->order_by('published','desc')
							   ->limit($pagination->items_per_page)
			        	       ->offset($pagination->offset)
			        	       ->find_all();
			}
			else 
			{
				$this->template->bind('content', $content);
				Alert::set(Alert::INFO, __('We did not find any advertisement for a desired search.'));
				$this->template->content = View::factory('pages/ad/advansed_search', array('cat'=>$cat, 'loc'=>$loc));
				return;
			}	

			$this->template->bind('content', $content);
			$this->template->content = View::factory('pages/ad/listing', array('ads'		=>$ads, 
																			   'cat'		=>$cat,
																			   'loc'		=>$loc, 
																			   'pagination'	=>$pagination, 
																			   'user'		=>$user));
        }
        else
        {
        	Breadcrumbs::add(Breadcrumb::factory()->set_title(__('Advansed Search')));
        	if($this->request->query('search'))
        	{
        		$unexisting_ad = $this->request->query('search');
        	}
        	else $unexisting_ad = NULL;

        	$this->template->content = View::factory('pages/ad/advansed_search', array('unexisting_ad'=>$unexisting_ad, 'cat'=>$cat, 'loc'=>$loc));
        }

	}

	
}// End ad controller

