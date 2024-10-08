<?php
namespace Salad\Core;

use App\Models\Content;

class Application
{
    public static Application $app;
    public static string $ROOT_DIR;
    
    public Response $response;
    public Request $request;
    public View $view;
    public Session $session;
    public Extension $extension;
    public Content $content;
    public Database $db;
    public FileUploader $uploader;
    
    public function __construct($rootDir)
    {
        self::$app = $this;
        self::$ROOT_DIR = $rootDir;
        
        $this->initializeCoreComponents();
    }
    
    protected function initializeCoreComponents()
    {
        $this->request = new Request();
        $this->response = new Response();
        $this->session = new Session();
        $this->uploader = new FileUploader();
        $this->extension = new Extension();
        $this->content = new Content();
        $this->view = new View();
        $this->db = new Database();
    }

    public function getBaseUrl(): string
    {
        $protocol = $this->isHttps() ? "https://" : "http://";
        $hostName = $_SERVER['HTTP_HOST'];
        $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
        return rtrim($protocol . $hostName . $scriptPath, '/');
    }
    
    protected function isHttps(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443;
    }

    public function renderPage(array $sections): void
    {
        $render = $this->renderExtensions($sections);
        $this->view->render("template/site/chunk/header", ["styles" => $render['style']]);
        $this->view->render("template/site/chunk/navigation");

        foreach ($sections as $section) {
            $this->renderSection($section);
        }

        $this->view->render("template/site/chunk/footer", ["script" => $render['script']]);
    }

    protected function renderExtensions(array $sections): array
    {
        $style = "";
        $script = "";
        foreach ($sections as $section) {
			if($this->checkExtensionEnabled($section['section'])){
				if ($section['type'] === "extension") {
					$package = $this->extension->getFeature($section['section']);
					$packagePath = self::$ROOT_DIR . "/vendor/" . $this->normalizePath($package['install-path']);
					$this->view->addViewPath($packagePath . '/src/Views');
					$style = file_get_contents($packagePath . "/src/" . $package['extra']['section']['style']);
					$script = file_get_contents($packagePath . "/src/" . $package['extra']['section']['script']);
				}
			}
        }
        return [
			"style" => $style,
			"script" => $script,
		];
    }

    protected function renderSection(array $section): void
    {
        if ($section['type'] === "extension") {
            $package = $this->extension->getFeature($section['section']);
			if($package && $this->checkExtensionEnabled($package['section'])){
				$this->view->render($package['extra']['section']['render']);
			}
        } elseif ($section['type'] === "content") {
            $content = $this->content->findById($section['section']);
            $this->view->render("template/site/chunk/content", ["data" => $content]);
        }
    }

    public function normalizePath(string $path): string
    {
        $parts = explode('/', $path);
        $stack = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                if (!empty($stack)) {
                    array_pop($stack);
                }
            } else {
                $stack[] = $part;
            }
        }
        return implode('/', $stack);
    }

	public function checkExtensionEnabled(string $name): string
	{
		$name = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $name));
		try {
			if(isset($_ENV['EXTENSION_' . $name]) && $_ENV['EXTENSION_' . $name] === 'true'){
			return true;
			}
			return false;
		} catch (\Throwable $th) {
			return false;
		}
	}

}
