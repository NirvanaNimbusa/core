<?php

class RouteTest extends PHPUnit_Framework_TestCase {

  public function __construct(){
    Options::set('core.response.autosend', false);
  }

	private function mock_request($uri, $method) {
		Filter::remove('core.request.method');
		Filter::remove('core.request.URI');
		Filter::add('core.request.URI', function ($x) use ($uri) {return $uri;});
		Filter::add('core.request.method', function ($x) use ($method) {return $method;});
	}

	public function testBasicRouting() {
    Route::reset();
    Options::set('core.response.autosend', false);
		Response::clean();
		Route::on('/', function () {
			return "index";
		});

    $route_start_event_passed = false;
    $route_end_event_passed = false;
    Route::onEvent('start',function() use (&$route_start_event_passed){ $route_start_event_passed = true; });
    Route::onEvent('end',function() use (&$route_end_event_passed){ $route_end_event_passed = true; });

		Route::dispatch('/', 'get');

    $this->assertTrue($route_start_event_passed, 'route_start_event');
    $this->assertTrue($route_end_event_passed, 'route_end_event');

    Route::off('start');
    Route::off('end');

		$this->assertEquals(Response::body(), 'index');
	}

	public function testAliasGet() {
    Route::reset();
    Options::set('core.response.autosend', false);
		Response::clean();
		Route::get('/', function () {
			return "index";
		});
		Route::dispatch('/', 'get');
		$this->assertEquals(Response::body(), 'index');
	}

	public function testAliasPost() {
    Route::reset();
    Options::set('core.response.autosend', false);
		Response::clean();
		Route::post('/', function () {
			return "index";
		});
		Route::dispatch('/', 'post');
		$this->assertEquals(Response::body(), 'index');
	}

	public function testRouteNotFound() {
    Route::reset();
    Options::set('core.response.autosend', false);
		Response::clean();
		$test = $this;
		Event::on(404, function () use (&$test) {
			$test->assertEquals(404, 404);
		});
		Route::dispatch('/this/is/a/404', 'get');
		Event::off(404);
	}

	public function testWildcardMethod() {
    Route::reset();
    Options::set('core.response.autosend', false);
		Route::any('/any', function () {return "ANY";});
		Response::clean();
		Route::dispatch('/any', 'patch');
		$this->assertEquals('ANY', Response::body(),'patch /any');
		Response::clean();
		Route::dispatch('/any', 'get');
    $this->assertEquals('ANY', Response::body(),'get /any');
		Response::clean();
		Route::dispatch('/any', 'post');
    $this->assertEquals('ANY', Response::body(),'post /any');
	}

	public function testParameterExtraction() {
    Route::reset();
    Options::set('core.response.autosend', false);
		Response::clean();
		Route::on('/post/:a/:b', function ($a, $b) {return "$b-$a";});
		Route::dispatch('/post/1324/fefifo', 'get');
		$this->assertEquals('fefifo-1324', Response::body());
	}

	public function testMiddlewares() {
    Route::reset();
    Options::set('core.response.autosend', false);
		Response::clean();
		Route::on('/middle', function () {return "-Test-";})
			->before(function () {echo 'AA';})
			->before(function () {echo 'B';})
			->after(function () {echo 'AA';})
			->after(function () {echo 'B';})
		;
		Route::dispatch('/middle', 'get');
		$this->assertEquals(Response::body(), 'BAA-Test-AAB');
	}

	public function testGroups() {
    Route::reset();
    Options::set('core.response.autosend', false);
		Response::clean();
		$this->mock_request('/api1/v1/info', 'get');
		$api = Route::group('/api1', function () {
			Route::on('/info', function () {echo "API-INFO";});
			Route::group('/v1', function () {
				Route::on('/', function () {echo "API-V1";});
				Route::on('/info', function () {echo "API-V1-INFO";});
			});
		});

		Route::dispatch('/api1/v1/info', 'get');
		$this->assertEquals(Response::body(), 'API-V1-INFO');
	}

  public function testNullParamerGroupIndex() {
    Route::reset();
    Options::set('core.response.autosend', false);
    Response::clean();
    $this->mock_request('/aaaaaa', 'get');
    $api = Route::group('/aaaaaa', function (){
      Route::on('/(:id)', function ($test=0) {echo "$test-API-INFO";});
    });
    Route::dispatch('/aaaaaa', 'get');
    $this->assertEquals('0-API-INFO',Response::body());
  }

  public function testGroupsSkipping() {
    Route::reset();
    Event::off(404);
    Options::set('core.response.autosend', false);
    Response::clean();
    $this->mock_request('/not_right', 'get');
    $self = $this;
    $api = Route::group('/not', function () use ($self) {
      $self->assertTrue(false,"This assert must be skipped to be ok."); // This is an error!
    });
    Route::dispatch('/not_right', 'get');
    $this->assertTrue(true); // Good.
  }

	public function testGroupsMiddlewares() {
    Route::reset();
    Options::set('core.response.autosend', false);
		Response::clean();
		$this->mock_request('/api2/v1/info', 'get');
		Route::group('/api2', function () {
			Route::on('/info', function () {echo "API-INFO";});
			Route::group('/v1', function () {
				Route::on('/', function () {echo "API-V1";});
				Route::on('/info', function () {echo "API-V1-INFO";});
			});
		})
			->before(function () {echo 'AA-';})
			->after(function () {echo '-BB';});

		Route::dispatch('/api2/v1/info', 'get');
		$this->assertEquals('AA-API-V1-INFO-BB',Response::body());
	}

  public function testStaticGroupsNesting() {
      Route::reset();
      Event::off(404);
      Options::set('core.response.autosend', false);
      Response::clean();
      $URI = '/r_a/r_b/r_c/r_d';
      $this->mock_request($URI, 'get');
      Route::group('/r_a', function () {
        Route::group('/r_b', function () {
          Route::group('/r_c', function () {
            Route::group('/r_d', function () {
              Route::on('/',function(){
                return "OK-STATIC";
              });
            });
          });
        });
      });
      Route::dispatch($URI, 'get');
      $this->assertEquals('OK-STATIC', Response::body());
    }

  public function testGroupsExtraction() {
    Route::reset();
    Options::set('core.response.autosend', false);
    Response::clean();
    $this->mock_request('/item/1/info', 'get');

    Route::group("/item(/:id)",function($id){

      Route::on("/",function() use ($id){
        return "$id";
      });

      Route::on("/:field",function($field) use ($id){
        return "{$id}->{$field}";
      });

    });

    Route::dispatch('/item/1/info', 'get');
    $this->assertEquals('1->info', Response::body());

    Response::clean();
    $this->mock_request('/ritem/1/', 'get');

    Route::group("/ritem(/:id)",function($id){

      Route::on("/",function() use ($id){
        return "$id";
      });

      Route::on("/:field",function($field) use ($id){
        return "{$id}->{$field}";
      });

    });

    Route::dispatch('/ritem/1/', 'get');
    $this->assertEquals('1', Response::body());
  }


   public function testDynamicGroupsNesting() {
      Route::reset();

      Event::off(404);
      Options::set('core.response.autosend', false);
      Response::clean();

      $URI = '/x_a/x_b/x_c/x_d';
      $this->mock_request($URI, 'get');

      Route::group('/x_:a', function ($a) {
        Route::group('/x_:b', function ($b) use ($a) {
          Route::group('/x_:c', function ($c) use ($a,$b) {
            Route::group('/x_:d', function ($d) use ($a,$b,$c) {
              Route::on('/',function() use ($a,$b,$c,$d){
                return "OK-DYNAMIC-$a$b$c$d";
              });
            });
          });
        });
      });

      Route::dispatch($URI, 'get');
      $this->assertEquals('OK-DYNAMIC-abcd', Response::body());
    }

   public function testFullyOptionalRoute() {
      Route::reset();

      Options::set('core.response.autosend', false);
      Options::set('core.route.pruning', false);

      Route::on('/', function (){
          return "INDEX";
      });

      Route::on('/(:optional)', function ($optional='0'){
          return "ROOT:OPTIONAL:$optional";
      });

      Route::group('/model', function () {

        Route::on('(/:slug)', function ($slug = null){
          return "SLUG:" . ($slug === null ? 'NULL' : $slug);
        });

        Route::on('/:slug/info', function ($slug){
          return "INFO:FOR:$slug";
        });

      });

      $URI = '/model/test/info';
      Response::clean();
      $this->mock_request($URI, 'get');
      Route::dispatch($URI, 'get');
      $this->assertEquals(Response::body(),'INFO:FOR:test',$URI);

      $URI = '/model';
      Response::clean();
      $this->mock_request($URI, 'get');
      Route::dispatch($URI, 'get');
      $this->assertEquals(Response::body(),'SLUG:NULL',$URI);

      $URI = '/model/test';
      Response::clean();
      $this->mock_request($URI, 'get');
      Route::dispatch($URI, 'get');
      $this->assertEquals(Response::body(),'SLUG:test',$URI);

      $URI = '/';
      Response::clean();
      $this->mock_request($URI, 'get');
      Route::dispatch($URI, 'get');
      $this->assertEquals(Response::body(),'INDEX',$URI);

      $URI = '/foobar';
      Response::clean();
      $this->mock_request($URI, 'get');
      Route::dispatch($URI, 'get');
      $this->assertEquals(Response::body(),'ROOT:OPTIONAL:foobar',$URI);

    }

    public function testTagsAndReverseRouting() {
      Route::reset();
      Options::set('core.response.autosend', false);
      Response::clean();

      Route::on('/', function ($id) {
        return "INDEX";
      })->tag("index");

      Route::on('/user/:id', function ($id) {
        return "USER{$id}";
      })->tag("user");

      // Test also grouped routes
      Route::group("/foo",function(){
        Route::on('/bar(/:alpha/(:beta))/baz', function ($alpha=0,$beta=0) {
          return "ALPHA{$alpha},BETA{$beta}";
        })->tag("foo");
      });


      $foo  = Route::tagged("foo");
      $this->assertInstanceOf(Route::class, $foo);

      $user = Route::tagged("user");
      $this->assertInstanceOf(Route::class, $user);

      $this->assertFalse(Route::tagged("unknown"),"Unknown tag");

      // Must return an URL
      $this->assertInstanceOf(URL::class, Route::URL('foo'));

      $this->assertEquals('/foo/bar/baz',         Route::URL('foo'));

      $this->assertEquals('/foo/bar/123/baz',     Route::URL('foo',[
        'alpha' => 123,
      ]));

      $this->assertEquals('/foo/bar/321/baz',     Route::URL('foo',[
        'beta' => 321,
      ]));

      $this->assertEquals('/foo/bar/123/321/baz', Route::URL('foo',[
        'alpha' => 123,
        'beta' => 321,
      ]));

      $this->assertEquals('/foo/bar/123/321/baz', Route::URL('foo',[
        'alpha' => 123,
        'beta' => 321,
      ]));

      $this->assertEquals('/user',                Route::URL('user'));
      $this->assertEquals('/user/a/b',            Route::URL('user',['id'=>'a/b']));

      $this->assertEquals('/',                    Route::URL('index'));

    }

    public function testRootExtraction() {
      Route::reset();
      Event::off(404);
      Options::set('core.response.autosend', false);
      Response::clean();

      $URI = '/base/12/alpha/beta';
      Response::clean();
      $this->mock_request($URI, 'get');

      Route::group("/base", function() {
        Route::get("/", function() {
           return "INDEX";
        });

        Route::get("/:id", function($id) {
           return "ROOT$id";
        })->rules(['id' => '\d+']);

        Route::get("/:id/alpha", function($id) {
           return "ALPHA$id";
        })->rules(['id' => '\d+']);

        Route::get("/:id/alpha/beta", function($id) {
           return "BETA$id";
        })->rules(['id' => '\d+']);

        Route::group("/alpha", function() {
          Route::get("/", function() {
             return "ALPHA-INDEX";
          });
          Route::get("/gamma", function() {
             return "ALPHA-GAMMA";
          });
          Route::get("/delta", function() {
             return "ALPHA-DELTA";
          });
        });


      });

      Route::dispatch($URI, 'get');
      $this->assertEquals('BETA12',Response::body(),$URI);

      $URI = '/fake/alpha/beta';
      Response::clean();
      $this->mock_request($URI, 'get');
      Route::dispatch($URI, 'get');
      $this->assertEquals('',Response::body(),$URI);

      $URI = '/base/alpha/gamma';
      Response::clean();
      $this->mock_request($URI, 'get');
      Route::dispatch($URI, 'get');
      $this->assertEquals('ALPHA-GAMMA',Response::body(),$URI);

    }

    public function testRoutePushes() {

      Route::reset();
      Options::set('core.response.autosend', false);
      Response::clean();

      Route::get('/', function () {
        return "index";
      })
      ->push("/main.css","style")
      ->push("/main.js","script")
      ->push([
        'font'  => ["/fonts/a.woff","/fonts/b.ttf"],
        'image' => "/imgs/test.png",
        0 => '/idk.txt',
      ]);

      Route::dispatch('/', 'get');
      $compiled_headers = [];
      foreach(Response::headers() as $key=>$vals){
        foreach($vals as $val){
          $compiled_headers[] = "$key: $val[0]";
        }
      }
      $compiled_headers = implode("\n",$compiled_headers);
      $this->assertContains('Link: </main.js>; rel=preload; as=script',      $compiled_headers, 'RoutePushes.1');
      $this->assertContains('Link: </fonts/a.woff>; rel=preload; as=font',   $compiled_headers, 'RoutePushes.2');
      $this->assertContains('Link: </fonts/b.ttf>; rel=preload; as=font',    $compiled_headers, 'RoutePushes.3');
      $this->assertContains('Link: </imgs/test.png>; rel=preload; as=image', $compiled_headers, 'RoutePushes.4');
      $this->assertContains('Link: </idk.txt>; rel=preload; as=text',        $compiled_headers, 'RoutePushes.5');

      Route::reset();
      Options::set('core.response.autosend', false);
      Response::clean();

      Route::group('',function(){
        Route::get('/', function () {
          return "index";
        });
      })
      ->push("/main.css","style")
      ->push("/main.js","script")
      ->push([
        0 => '/idk.txt',
        'font'  => ["/fonts/a.woff","/fonts/b.ttf"],
        'image' => "/imgs/test.png",
      ]);

      Route::dispatch('/', 'get');
      $compiled_headers = [];
      foreach(Response::headers() as $key=>$vals){
        foreach($vals as $val){
          $compiled_headers[] = "$key: $val[0]";
        }
      }
      $compiled_headers = implode("\n",$compiled_headers);
      $this->assertContains('Link: </main.js>; rel=preload; as=script',      $compiled_headers, 'RouteGroupPushes.1');
      $this->assertContains('Link: </fonts/a.woff>; rel=preload; as=font',   $compiled_headers, 'RouteGroupPushes.2');
      $this->assertContains('Link: </fonts/b.ttf>; rel=preload; as=font',    $compiled_headers, 'RouteGroupPushes.3');
      $this->assertContains('Link: </imgs/test.png>; rel=preload; as=image', $compiled_headers, 'RouteGroupPushes.4');
      $this->assertContains('Link: </idk.txt>; rel=preload; as=text',        $compiled_headers, 'RouteGroupPushes.5');
    }


    public function testComplexNesting() {
      Route::reset();
      Event::off(404);
      Options::set('core.response.autosend', false);
      Response::clean();

      $make_tree = function($base,$cb=null){
        Route::group($base, function() use ($cb,$base) {
          Route::get("/", function() {
             return "INDEX";
          });

          Route::get("/:id", function($id) {
             return "ROOT$id";
          })->rules(['id' => '\d+']);

          Route::get("/:id/alpha", function($id) {
             return "ALPHA$id";
          })->rules(['id' => '\d+']);

          Route::get("/:id/alpha/beta", function($id) {
             return "BETA$id";
          })->rules(['id' => '\d+']);

          Route::group("/alpha", function() use ($cb,$base){
            Route::get("/", function() {
               return "ALPHA-INDEX";
            });
            Route::get("/gamma", function() {
               return "ALPHA-GAMMA";
            });
            Route::get("/delta", function() use ($base) {
               return "ALPHA-DELTA($base)";
            });

            if ($cb) $cb();
          });
        });
      };

      $URI = '/A1/alpha/A2/alpha/A3/alpha/delta';
      Response::clean();
      $this->mock_request($URI, 'get');

      $make_tree("/A1",function() use ($make_tree){
        $make_tree("/A2",function() use ($make_tree){
          $make_tree("/A3");
        });
      });

      Route::dispatch($URI, 'get');
      $this->assertEquals('ALPHA-DELTA(/A3)',Response::body(),$URI);

    }
}
