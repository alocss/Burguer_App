<?php
declare(strict_types=1);

namespace House;

use PDO;

final class App
{
    private PDO $db;
    public function __construct() { $this->db = Database::connection(); }

    public function run(): void
    {
        $path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') Security::verifyCsrf();

        if (preg_match('#^/uploads/([a-f0-9]{32}\.(?:jpg|png|webp))$#', $path, $m)) $this->serveUpload($m[1]);
        elseif ($path === '/') $this->home();
        elseif ($path === '/cart' && $method === 'POST') $this->cartAdd();
        elseif ($path === '/cart/remove' && $method === 'POST') $this->cartRemove();
        elseif ($path === '/checkout' && $method === 'GET') $this->checkout();
        elseif ($path === '/checkout' && $method === 'POST') $this->placeOrder();
        elseif ($path === '/login') $this->login(false);
        elseif ($path === '/register') $this->register();
        elseif ($path === '/logout' && $method === 'POST') $this->logout();
        elseif (preg_match('#^/pedido/([A-Z0-9]+)$#', $path, $m)) $this->track($m[1]);
        elseif ($path === '/admin/login') $this->login(true);
        elseif ($path === '/admin') $this->admin();
        elseif ($path === '/admin/action' && $method === 'POST') $this->adminAction();
        else { http_response_code(404); $this->layout('Página não encontrada', '<section class="empty"><h1>Página não encontrada</h1><a class="primary" href="/">Voltar</a></section>'); }
    }

    private function home(): void
    {
        $products = $this->db->query('SELECT p.*, c.name category FROM products p JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND p.sold_out=0 ORDER BY p.featured DESC,p.id')->fetchAll();
        $addonRows = $this->db->query('SELECT pag.product_id,g.id group_id,g.name group_name,g.min_choices,g.max_choices,g.required,a.id addon_id,a.name addon_name,a.price_cents FROM product_addon_groups pag JOIN addon_groups g ON g.id=pag.group_id AND g.active=1 JOIN addons a ON a.group_id=g.id AND a.active=1 ORDER BY pag.product_id,g.id,a.id')->fetchAll();
        $productAddons = [];
        foreach ($addonRows as $addon) {
            $productId = (int)$addon['product_id'];
            $groupId = (int)$addon['group_id'];
            $productAddons[$productId][$groupId]['group'] = $addon;
            $productAddons[$productId][$groupId]['options'][] = $addon;
        }
        $groups = [];
        foreach ($products as $product) $groups[$product['category']][] = $product;
        ob_start(); ?>
        <section class="hero"><img src="/assets/hero.jpg" alt="Hambúrguer artesanal Burguer App"><div><span>CARNE, FOGO & PERSONALIDADE</span><h1>A NOITE PEDE<br><em>BURGUER.</em></h1><p>Hambúrguer artesanal de verdade, criado na brasa e entregue quente até você.</p><a class="primary" href="#cardapio">Pedir agora</a></div></section>
        <section class="benefits"><article><b>Horário</b><span>18h às 23h30</span></article><article><b>Funcionamento</b><span>Terça a domingo</span></article><article><b>Localização</b><span>Rua das Brasas, 147 — Centro</span></article><article><b>Retirada grátis</b><span>Seu pedido pronto e quentinho</span></article></section>
        <nav class="categories" aria-label="Categorias"><b>ESCOLHA SUA FOME</b><a class="active" href="#cardapio">Todos</a><?php foreach(array_keys($groups) as $category):?><a href="#<?=Security::e($this->slug($category))?>"><?=Security::e($category)?></a><?php endforeach;?></nav>
        <section class="catalog" id="cardapio"><span class="eyebrow">NOSSO CARDÁPIO</span><h2>Feitos para marcar.</h2>
        <?php foreach ($groups as $category => $items): ?><div class="product-row" id="<?=Security::e($this->slug($category))?>"><header><h3><?=Security::e(mb_strtoupper($category))?></h3><span>← &nbsp; →</span></header><div class="products">
        <?php foreach ($items as $p): $dialogId='customize-'.(int)$p['id']; ?><article class="product"><img src="<?=Security::e($p['image_path'])?>" alt="<?=Security::e($p['name'])?>"><div><small><?=Security::e($p['category'])?></small><h3><?=Security::e($p['name'])?></h3><p><?=Security::e($p['description'])?></p><footer><strong><?=self::money((int)$p['price_cents'])?></strong><button class="primary customize-trigger" type="button" data-dialog="<?=$dialogId?>" aria-haspopup="dialog">Adicionar</button></footer></div></article>
        <dialog class="customize-dialog" id="<?=$dialogId?>" aria-labelledby="<?=$dialogId?>-title"><div class="customize-shell"><div class="customize-visual"><img src="<?=Security::e($p['image_path'])?>" alt=""></div><form class="customize-form" method="post" action="/cart" data-base-price="<?=(int)$p['price_cents']?>"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><header><div><span class="eyebrow"><?=Security::e(mb_strtoupper($p['category']))?></span><h2 id="<?=$dialogId?>-title"><?=Security::e($p['name'])?></h2><p><?=Security::e($p['description'])?></p></div><button class="dialog-close" type="button" aria-label="Fechar personalização">×</button></header><div class="customize-scroll">
        <?php foreach($productAddons[(int)$p['id']]??[] as $addonGroup): $group=$addonGroup['group']; ?><fieldset data-max-choices="<?=(int)$group['max_choices']?>"><legend><span><?=Security::e($group['group_name'])?></span><small><?=(int)$group['min_choices']>0?'Escolha ao menos '.(int)$group['min_choices']:'Opcional'?></small></legend><?php foreach($addonGroup['options'] as $option):?><label class="addon-option"><input type="checkbox" name="addon_ids[]" value="<?=(int)$option['addon_id']?>" data-price="<?=(int)$option['price_cents']?>"><span><?=Security::e($option['addon_name'])?></span><b><?=((int)$option['price_cents'])>0?'+ '.self::money((int)$option['price_cents']):'Grátis'?></b></label><?php endforeach;?></fieldset><?php endforeach;?>
        <label class="notes-field">Observação<textarea name="notes" maxlength="300" rows="3" placeholder="Ex.: ponto da carne, molho separado"></textarea><small><span>0</span>/300</small></label></div><footer class="customize-actions"><div class="quantity-control" aria-label="Quantidade"><button type="button" data-quantity="minus" aria-label="Diminuir quantidade">−</button><output>1</output><input type="hidden" name="quantity" value="1"><button type="button" data-quantity="plus" aria-label="Aumentar quantidade">+</button></div><button class="primary add-customized" type="submit"><span>Adicionar</span><strong><?=self::money((int)$p['price_cents'])?></strong></button></footer></form></div></dialog><?php endforeach; ?>
        </div></div><?php endforeach; ?></section>
        <section class="home-promo" id="promocoes"><div><span class="eyebrow">PROMOÇÃO DA CASA</span><h2>Mais sabor. Melhor junto.</h2><p>Escolha seu burger favorito, complete com batata e bebida e transforme a noite.</p><a class="primary" href="#combos">Ver os combos</a></div><img src="/assets/combo.jpg" alt="Combo Burguer App"></section>
        <section class="delivery-info" id="duvidas"><span class="eyebrow">CHEGAMOS ATÉ VOCÊ</span><h2>Da nossa brasa para sua casa.</h2><div><article><b>01</b><h3>Onde entregamos</h3><p>Simões, Paripe e Areia Branca. Taxa calculada no checkout.</p></article><article><b>02</b><h3>Como pagar</h3><p>Pix, dinheiro, débito ou crédito na entrega e retirada.</p></article><article><b>03</b><h3>Retire sem taxa</h3><p>Rua das Brasas, 147 — Centro. Seu pedido pronto e quentinho.</p></article></div></section>
        <section class="faq"><span class="eyebrow">DÚVIDAS FREQUENTES</span><h2>Tudo certo para pedir.</h2><div><details open><summary>Qual é o horário de funcionamento?</summary><p>Terça a domingo, das 18h às 23h30.</p></details><details><summary>Quais bairros recebem entrega?</summary><p>Simões, Paripe e Areia Branca.</p></details><details><summary>Posso retirar meu pedido?</summary><p>Sim. A retirada no balcão não tem taxa.</p></details><details><summary>Quais são as formas de pagamento?</summary><p>Pix, dinheiro, débito e crédito na entrega ou retirada.</p></details></div></section>
        <footer class="site-footer"><div><b>Burguer App</b><p>Rua das Brasas, 147 — Centro</p></div><div><b>Atendimento</b><p>Terça a domingo • 18h às 23h30</p></div><p>© <?=date('Y')?> Burguer App.</p></footer><?php
        $this->layout('Burguer App — Delivery', ob_get_clean());
    }

    private function cartAdd(): never
    {
        $id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $quantity = max(1, min(10, (int)($_POST['quantity'] ?? 1)));
        $notes = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 300);
        $addonIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['addon_ids'] ?? [])), fn(int $value): bool => $value > 0)));
        $stmt = $this->db->prepare('SELECT id FROM products WHERE id=:id AND active=1 AND sold_out=0');
        $stmt->execute(['id'=>$id]);
        if (!$stmt->fetch()) $this->flash('Produto indisponível.', 'error', '/');
        $groups = $this->productAddonGroups((int)$id);
        $selectedByGroup = [];
        foreach ($addonIds as $addonId) {
            if (!isset($groups['options'][$addonId])) $this->flash('Uma personalização selecionada não está disponível.', 'error', '/#cardapio');
            $selectedByGroup[$groups['options'][$addonId]['group_id']][] = $addonId;
        }
        foreach ($groups['groups'] as $groupId => $group) {
            $count = count($selectedByGroup[$groupId] ?? []);
            if ($count < (int)$group['min_choices'] || $count > (int)$group['max_choices']) $this->flash('Revise as opções de '.$group['name'].'.', 'error', '/#cardapio');
        }
        sort($addonIds);
        $lineKey = hash('sha256', (int)$id.'|'.implode(',', $addonIds).'|'.$notes);
        $existing = $_SESSION['cart'][$lineKey]['quantity'] ?? 0;
        $_SESSION['cart'][$lineKey] = ['product_id'=>(int)$id,'quantity'=>min(10,(int)$existing+$quantity),'addon_ids'=>$addonIds,'notes'=>$notes];
        $this->flash('Produto adicionado à sacola.', 'success', '/#cardapio');
    }

    private function cartRemove(): never
    {
        $lineKey = preg_replace('/[^a-f0-9]/', '', (string)($_POST['line_key'] ?? ''));
        if (strlen($lineKey) === 64) unset($_SESSION['cart'][$lineKey]);
        $this->flash('Item removido.', 'success', '/checkout');
    }

    private function checkout(): void
    {
        Auth::requireUser();
        $_SESSION['checkout_nonce'] ??= bin2hex(random_bytes(16));
        [$items,$subtotal] = $this->cartData();
        $areas = $this->db->query('SELECT * FROM delivery_areas WHERE active=1 ORDER BY name')->fetchAll();
        ob_start(); ?>
        <section class="checkout"><span class="eyebrow">CHECKOUT SEGURO</span><h1>Finalize seu pedido</h1>
        <?php if (!$items): ?><p>Sua sacola está vazia.</p><a class="primary" href="/">Ver cardápio</a><?php else: ?>
        <div class="checkout-grid"><div><?php foreach($items as $item): ?><article class="cart-row"><img src="<?=Security::e($item['image_path'])?>" alt=""><div><b><?=Security::e($item['name'])?></b><span><?= (int)$item['quantity']?> × <?=self::money((int)$item['price_cents'])?></span><?php if($item['addons']):?><small><?=Security::e(implode(' • ',array_column($item['addons'],'name')))?></small><?php endif;?><?php if($item['notes']!==''):?><small>Observação: <?=Security::e($item['notes'])?></small><?php endif;?></div><strong><?=self::money((int)$item['line_total'])?></strong><form method="post" action="/cart/remove"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><input type="hidden" name="line_key" value="<?=Security::e($item['line_key'])?>"><button>Remover</button></form></article><?php endforeach; ?></div>
        <form class="order-form" method="post" action="/checkout"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><input type="hidden" name="idempotency_key" value="<?=Security::e(hash('sha256',session_id().'|'.implode(',',array_keys($_SESSION['cart'] ?? [])).'|'.$subtotal))?>"><label>Recebimento<select name="fulfillment" id="fulfillment"><option value="delivery">Entrega</option><option value="pickup">Retirada grátis</option></select></label><label>Bairro<select name="area_id"><?php foreach($areas as $a):?><option value="<?=(int)$a['id']?>"><?=Security::e($a['name'])?> — <?=self::money((int)$a['fee_cents'])?></option><?php endforeach;?></select></label><label>Rua<input name="street" maxlength="160" required></label><label>Número<input name="number" maxlength="30" required></label><label>Referência<input name="reference" maxlength="160"></label><label>Cupom<input name="coupon" maxlength="40"></label><label>Pagamento<select name="payment_method"><option value="pix_delivery">Pix na entrega</option><option value="cash">Dinheiro</option><option value="debit_delivery">Débito na entrega</option><option value="credit_delivery">Crédito na entrega</option></select></label><p>Subtotal <strong><?=self::money($subtotal)?></strong></p><button class="primary" type="submit">Confirmar pedido</button></form></div><?php endif; ?></section><?php
        $this->layout('Finalizar pedido', ob_get_clean());
    }

    private function placeOrder(): never
    {
        Auth::requireUser();
        [$items,$subtotal] = $this->cartData();
        if (!$items) $this->flash('Sua sacola está vazia.', 'error', '/');
        $fulfillment = in_array($_POST['fulfillment'] ?? '', ['delivery','pickup'], true) ? $_POST['fulfillment'] : 'delivery';
        $payment = in_array($_POST['payment_method'] ?? '', ['cash','pix_delivery','debit_delivery','credit_delivery'], true) ? $_POST['payment_method'] : 'pix_delivery';
        $area = null; $fee = 0; $addressId = null;
        if ($fulfillment === 'delivery') {
            $stmt=$this->db->prepare('SELECT * FROM delivery_areas WHERE id=:id AND active=1');$stmt->execute(['id'=>(int)($_POST['area_id']??0)]);$area=$stmt->fetch();
            if (!$area || trim($_POST['street']??'')==='' || trim($_POST['number']??'')==='') $this->flash('Preencha o endereço de entrega.', 'error', '/checkout');
            $fee=(int)$area['fee_cents']; if($subtotal<(int)$area['min_order_cents'])$this->flash('Pedido abaixo do mínimo da região.', 'error', '/checkout');
        }
        $discount=0;$couponCode=mb_strtoupper(trim($_POST['coupon']??''));
        if($couponCode!==''){$s=$this->db->prepare('SELECT * FROM coupons WHERE code=:code AND active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (expires_at IS NULL OR expires_at>=NOW()) AND (usage_limit IS NULL OR used_count<usage_limit)');$s->execute(['code'=>$couponCode]);$c=$s->fetch();if($c&&$subtotal>=(int)$c['min_order_cents']){$discount=$c['type']==='percent'?(int)round($subtotal*(int)$c['value']/100):($c['type']==='fixed'?min($subtotal,(int)$c['value']):0);if($c['type']==='free_delivery')$fee=0;}}
        $total=max(0,$subtotal-$discount+$fee);$key=(string)($_POST['idempotency_key']??'');if(!preg_match('/^[a-f0-9]{64}$/',$key))$this->flash('Checkout inválido.', 'error', '/checkout');
        $existing=$this->db->prepare('SELECT public_number FROM orders WHERE idempotency_key=:key AND user_id=:user');$existing->execute(['key'=>$key,'user'=>Auth::user()['id']]);if($existingNumber=$existing->fetchColumn())Auth::redirect('/pedido/'.$existingNumber);
        $number='HB'.date('ymd').strtoupper(substr(bin2hex(random_bytes(4)),0,6));$user=Auth::user();
        $orderId=Database::transaction(function(PDO $db)use($items,$subtotal,$discount,$fee,$total,$key,$number,$user,$fulfillment,$payment,$area,&$addressId){
            if($fulfillment==='delivery'){$s=$db->prepare('INSERT INTO addresses(user_id,delivery_area_id,street,number,reference)VALUES(:u,:a,:s,:n,:r)');$s->execute(['u'=>$user['id'],'a'=>$area['id'],'s'=>mb_substr(trim($_POST['street']),0,160),'n'=>mb_substr(trim($_POST['number']),0,30),'r'=>mb_substr(trim($_POST['reference']??''),0,160)]);$addressId=(int)$db->lastInsertId();}
            $s=$db->prepare('INSERT INTO orders(public_number,idempotency_key,user_id,address_id,fulfillment,payment_method,payment_status,subtotal_cents,discount_cents,delivery_fee_cents,total_cents)VALUES(:num,:key,:u,:addr,:f,:pm,\'pending\',:sub,:disc,:fee,:total)');$s->execute(['num'=>$number,'key'=>$key,'u'=>$user['id'],'addr'=>$addressId,'f'=>$fulfillment,'pm'=>$payment,'sub'=>$subtotal,'disc'=>$discount,'fee'=>$fee,'total'=>$total]);$oid=(int)$db->lastInsertId();
            $si=$db->prepare('INSERT INTO order_items(order_id,product_id,product_name,unit_price_cents,quantity,total_cents)VALUES(:o,:p,:n,:unit,:q,:total)');
            $sa=$db->prepare('INSERT INTO order_item_addons(order_item_id,addon_id,addon_name,unit_price_cents,quantity,total_cents)VALUES(:item,:addon,:name,:price,:quantity,:total)');
            $sc=$db->prepare('INSERT INTO order_item_customizations(order_item_id,notes)VALUES(:item,:notes)');
            foreach($items as$i){
                $si->execute(['o'=>$oid,'p'=>$i['id'],'n'=>$i['name'],'unit'=>$i['price_cents'],'q'=>$i['quantity'],'total'=>$i['line_total']]);
                $orderItemId=(int)$db->lastInsertId();
                foreach($i['addons'] as$addon)$sa->execute(['item'=>$orderItemId,'addon'=>$addon['id'],'name'=>$addon['name'],'price'=>$addon['price_cents'],'quantity'=>$i['quantity'],'total'=>(int)$addon['price_cents']*(int)$i['quantity']]);
                if($i['notes']!=='')$sc->execute(['item'=>$orderItemId,'notes'=>$i['notes']]);
            }
            $db->prepare('INSERT INTO order_status_history(order_id,actor_id,new_status)VALUES(:o,:u,\'received\')')->execute(['o'=>$oid,'u'=>$user['id']]);return $oid;
        });
        $_SESSION['cart']=[];unset($_SESSION['checkout_nonce']);session_regenerate_id(true);Auth::redirect('/pedido/'.$number);
    }

    private function login(bool $admin): void
    {
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            if(Auth::attempt($_POST['email']??'',$_POST['password']??'',$admin)) Auth::redirect($admin?'/admin':'/checkout');
            $error='Credenciais inválidas ou acesso não autorizado.';
        }
        ob_start();?><section class="auth-card"><img src="/assets/logo-burguer-app.jpg" alt="Burguer App"><span class="eyebrow"><?= $admin?'ACESSO ADMINISTRATIVO':'ÁREA DO CLIENTE'?></span><h1>Entrar</h1><?php if(isset($error)):?><p class="alert error"><?=Security::e($error)?></p><?php endif;?><form method="post"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><label>E-mail<input type="email" name="email" autocomplete="email" required></label><label>Senha<input type="password" name="password" autocomplete="current-password" required></label><button class="primary">Entrar</button></form><?php if(!$admin):?><p>Não possui conta? <a href="/register">Cadastre-se</a></p><?php endif;?></section><?php $this->layout('Entrar',ob_get_clean(),$admin);
    }

    private function register(): void
    {
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){$name=trim($_POST['name']??'');$email=mb_strtolower(trim($_POST['email']??''));$phone=trim($_POST['phone']??'');$pass=$_POST['password']??'';if(mb_strlen($name)<2||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<12)$error='Revise os dados. A senha deve ter pelo menos 12 caracteres.';else{try{$s=$this->db->prepare('INSERT INTO users(name,email,phone,password_hash)VALUES(:n,:e,:p,:h)');$s->execute(['n'=>mb_substr($name,0,120),'e'=>$email,'p'=>mb_substr($phone,0,30),'h'=>password_hash($pass,PASSWORD_ARGON2ID)]);Auth::attempt($email,$pass);Auth::redirect('/checkout');}catch(\PDOException){$error='Não foi possível criar a conta com esses dados.';}}}
        ob_start();?><section class="auth-card"><span class="eyebrow">CADASTRO SEGURO</span><h1>Crie sua conta</h1><?php if(isset($error)):?><p class="alert error"><?=Security::e($error)?></p><?php endif;?><form method="post"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><label>Nome<input name="name" maxlength="120" required></label><label>E-mail<input type="email" name="email" maxlength="190" required></label><label>WhatsApp<input name="phone" maxlength="30"></label><label>Senha<input type="password" name="password" minlength="12" autocomplete="new-password" required></label><button class="primary">Criar conta</button></form></section><?php $this->layout('Cadastro',ob_get_clean());
    }

    private function logout(): never { Security::logout(); Auth::redirect('/'); }

    private function track(string $number): void
    {
        Auth::requireUser();$s=$this->db->prepare('SELECT * FROM orders WHERE public_number=:n AND user_id=:u');$s->execute(['n'=>$number,'u'=>Auth::user()['id']]);$o=$s->fetch();if(!$o){http_response_code(404);$this->layout('Pedido não encontrado','<section class="empty"><h1>Pedido não encontrado</h1></section>');return;}
        ob_start();?><section class="tracking"><span class="eyebrow">PEDIDO <?=Security::e($o['public_number'])?></span><h1><?=Security::e(self::statusLabel($o['status']))?></h1><p>Total: <strong><?=self::money((int)$o['total_cents'])?></strong></p><div class="timeline"><?php foreach(['received','confirmed','preparing','out_for_delivery','delivered']as$st):?><div class="<?=$o['status']===$st?'active':''?>"><b>●</b><span><?=Security::e(self::statusLabel($st))?></span></div><?php endforeach;?></div><a class="primary" href="/">Voltar ao cardápio</a></section><?php $this->layout('Acompanhar pedido',ob_get_clean());
    }

    private function cartData(): array
    {
        $cart=$_SESSION['cart']??[];
        if(!$cart)return[[],0];
        $normalized=[];
        foreach($cart as$key=>$line){
            if(is_array($line)){
                $productId=(int)($line['product_id']??0);
                $lineKey=preg_match('/^[a-f0-9]{64}$/',(string)$key)?(string)$key:hash('sha256',$productId.'|legacy|'.$key);
                $normalized[$lineKey]=['product_id'=>$productId,'quantity'=>max(1,min(10,(int)($line['quantity']??1))),'addon_ids'=>array_values(array_unique(array_map('intval',(array)($line['addon_ids']??[])))),'notes'=>mb_substr(trim((string)($line['notes']??'')),0,300)];
            }else{
                $productId=(int)$key;
                $lineKey=hash('sha256',$productId.'||');
                $normalized[$lineKey]=['product_id'=>$productId,'quantity'=>max(1,min(10,(int)$line)),'addon_ids'=>[],'notes'=>''];
            }
        }
        $ids=array_values(array_unique(array_filter(array_column($normalized,'product_id'))));
        if(!$ids)return[[],0];
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $s=$this->db->prepare("SELECT id,name,price_cents,image_path FROM products WHERE active=1 AND sold_out=0 AND id IN ($marks)");
        $s->execute($ids);
        $products=[];foreach($s->fetchAll()as$product)$products[(int)$product['id']]=$product;
        $items=[];$subtotal=0;
        foreach($normalized as$lineKey=>$line){
            if(!isset($products[$line['product_id']]))continue;
            $p=$products[$line['product_id']];$allowed=$this->productAddonGroups((int)$p['id']);$addons=[];$addonTotal=0;
            foreach($line['addon_ids']as$addonId){if(isset($allowed['options'][$addonId])){$addon=$allowed['options'][$addonId];$addons[]=['id'=>(int)$addon['id'],'name'=>$addon['name'],'price_cents'=>(int)$addon['price_cents']];$addonTotal+=(int)$addon['price_cents'];}}
            $p['line_key']=$lineKey;$p['quantity']=$line['quantity'];$p['notes']=$line['notes'];$p['addons']=$addons;$p['base_price_cents']=(int)$p['price_cents'];$p['price_cents']=(int)$p['price_cents']+$addonTotal;$p['line_total']=(int)$p['price_cents']*(int)$p['quantity'];$subtotal+=$p['line_total'];$items[]=$p;
        }
        $_SESSION['cart']=$normalized;
        return[$items,$subtotal];
    }

    private function productAddonGroups(int $productId): array
    {
        $stmt=$this->db->prepare('SELECT g.id group_id,g.name group_name,g.min_choices,g.max_choices,a.id addon_id,a.name addon_name,a.price_cents FROM product_addon_groups pag JOIN addon_groups g ON g.id=pag.group_id AND g.active=1 JOIN addons a ON a.group_id=g.id AND a.active=1 WHERE pag.product_id=:product ORDER BY g.id,a.id');
        $stmt->execute(['product'=>$productId]);$groups=[];$options=[];
        foreach($stmt->fetchAll()as$row){$groupId=(int)$row['group_id'];$groups[$groupId]=['name'=>$row['group_name'],'min_choices'=>(int)$row['min_choices'],'max_choices'=>(int)$row['max_choices']];$options[(int)$row['addon_id']]=['id'=>(int)$row['addon_id'],'group_id'=>$groupId,'name'=>$row['addon_name'],'price_cents'=>(int)$row['price_cents']];}
        return['groups'=>$groups,'options'=>$options];
    }

    private function admin(): void
    {
        Auth::requireAdmin();$page=$_GET['page']??'overview';$allowed=['overview','orders','products','categories','addons','coupons','areas','banners','settings','users'];if(!in_array($page,$allowed,true))$page='overview';
        $orders=$this->db->query('SELECT o.*,u.name customer FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 100')->fetchAll();$products=$this->db->query('SELECT p.*,c.name category FROM products p JOIN categories c ON c.id=p.category_id ORDER BY p.id DESC')->fetchAll();$categories=$this->db->query('SELECT c.*,COUNT(p.id) product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.sort_order')->fetchAll();$addons=$this->db->query('SELECT g.*,COUNT(a.id) option_count FROM addon_groups g LEFT JOIN addons a ON a.group_id=g.id GROUP BY g.id ORDER BY g.id DESC')->fetchAll();$coupons=$this->db->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll();$areas=$this->db->query('SELECT * FROM delivery_areas ORDER BY name')->fetchAll();$users=$this->db->query("SELECT id,name,email,role,active,created_at FROM users WHERE role!='customer' ORDER BY id DESC")->fetchAll();
        ob_start();?><div class="admin-shell"><aside><img src="/assets/logo-burguer-app.jpg"><b>APP</b><span>PAINEL DE GESTÃO</span><nav><?php foreach(['overview'=>'Visão geral','orders'=>'Pedidos','products'=>'Produtos','categories'=>'Categorias','addons'=>'Adicionais','coupons'=>'Cupons','areas'=>'Áreas de entrega','banners'=>'Banners e promoções','settings'=>'Configurações','users'=>'Usuários']as$k=>$v):?><a class="<?=$page===$k?'active':''?>" href="/admin?page=<?=$k?>"><?=Security::e($v)?></a><?php endforeach;?></nav><a href="/">← Ver loja</a></aside><main><header><div><span class="eyebrow">BURGUER APP</span><h1><?=Security::e(mb_strtoupper($page))?></h1></div><form method="post" action="/logout"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><button>Sair</button></form></header>
        <?php if($page==='overview'):$revenue=array_sum(array_column($orders,'total_cents'));?><section class="metrics"><article><span>Pedidos</span><strong><?=count($orders)?></strong></article><article><span>Faturamento</span><strong><?=self::money($revenue)?></strong></article><article><span>Em preparação</span><strong><?=count(array_filter($orders,fn($o)=>$o['status']==='preparing'))?></strong></article></section><?php endif;?>
        <?php if($page==='orders'):?><section class="kanban"><?php foreach(['received','confirmed','preparing','out_for_delivery','delivered']as$st):?><div><h2><?=Security::e(self::statusLabel($st))?></h2><?php foreach(array_filter($orders,fn($o)=>$o['status']===$st)as$o):?><article><b><?=Security::e($o['public_number'])?></b><span><?=Security::e($o['customer'])?></span><strong><?=self::money((int)$o['total_cents'])?></strong><form method="post" action="/admin/action"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><input type="hidden" name="action" value="order_status"><input type="hidden" name="id" value="<?=(int)$o['id']?>"><select name="status"><?php foreach(['received','confirmed','preparing','out_for_delivery','delivered','cancelled','rejected']as$x):?><option value="<?=$x?>" <?=$x===$o['status']?'selected':''?>><?=Security::e(self::statusLabel($x))?></option><?php endforeach;?></select><button>Atualizar</button></form></article><?php endforeach;?></div><?php endforeach;?></section><?php endif;?>
        <?php if($page==='products'):?><form class="settings" method="post" action="/admin/action" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><input type="hidden" name="action" value="product_create"><h2>Novo produto</h2><label>Nome<input name="name" maxlength="140" required></label><label>Descrição<input name="description" maxlength="500" required></label><label>Categoria<select name="category_id"><?php foreach($categories as$c):?><option value="<?=(int)$c['id']?>"><?=Security::e($c['name'])?></option><?php endforeach;?></select></label><label>Preço em centavos<input type="number" min="1" name="price_cents" required></label><label>Imagem JPG, PNG ou WebP<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><button class="primary">Cadastrar produto</button></form><section class="admin-grid"><?php foreach($products as$p):?><article><img src="<?=Security::e($p['image_path'])?>"><h3><?=Security::e($p['name'])?></h3><p><?=Security::e($p['description'])?></p><strong><?=self::money((int)$p['price_cents'])?></strong><form method="post" action="/admin/action"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><input type="hidden" name="action" value="product_toggle"><input type="hidden" name="id" value="<?=(int)$p['id']?>"><button><?=$p['active']?'Desativar':'Ativar'?></button></form></article><?php endforeach;?></section><?php endif;?>
        <?php if($page==='categories'):?><?=$this->createForm('category_create','Nova categoria',[['name','Nome','text'],['sort_order','Ordem','number']])?><?php $this->adminTable($categories,['name','product_count','sort_order','active']);endif;?>
        <?php if($page==='coupons'):?><?=$this->createForm('coupon_create','Novo cupom',[['code','Código','text'],['value','Desconto percentual','number'],['min_order_cents','Pedido mínimo em centavos','number'],['usage_limit','Limite de usos','number']])?><?php $this->adminTable($coupons,['code','type','value','used_count','active']);endif;?>
        <?php if($page==='areas'):?><?=$this->createForm('area_create','Nova área',[['name','Bairro ou região','text'],['fee_cents','Taxa em centavos','number'],['min_order_cents','Pedido mínimo em centavos','number'],['eta_minutes','Prazo em minutos','number']])?><?php $this->adminTable($areas,['name','fee_cents','min_order_cents','eta_minutes','active']);endif;?>
        <?php if($page==='banners'):$banners=$this->db->query('SELECT * FROM banners ORDER BY sort_order,id DESC')->fetchAll();?><form class="settings" method="post" action="/admin/action" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><input type="hidden" name="action" value="banner_create"><h2>Nova campanha</h2><label>Título<input name="title" maxlength="140" required></label><label>Texto<input name="body" maxlength="300"></label><label>Destino<input name="destination_url" maxlength="255"></label><label>Ordem<input type="number" name="sort_order" value="0"></label><label>Imagem<input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label><button class="primary">Criar campanha</button></form><?php $this->adminTable($banners,['title','destination_url','sort_order','active','created_at']);endif;?>
        <?php if($page==='users'):?><?=$this->createForm('user_create','Novo usuário administrativo',[['name','Nome','text'],['email','E-mail','email'],['password','Senha inicial, mínimo 12 caracteres','password']])?><?php $this->adminTable($users,['name','email','role','active','created_at']);endif;?>
        <?php if($page==='addons'):?><?=$this->createForm('addon_group_create','Novo grupo de adicionais',[['name','Nome do grupo','text'],['min_choices','Mínimo','number'],['max_choices','Máximo','number']])?><form class="settings" method="post" action="/admin/action"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><input type="hidden" name="action" value="addon_create"><h2>Nova opção</h2><label>Grupo<select name="group_id"><?php foreach($addons as$g):?><option value="<?=(int)$g['id']?>"><?=Security::e($g['name'])?></option><?php endforeach;?></select></label><label>Nome<input name="name" maxlength="120" required></label><label>Preço em centavos<input type="number" min="0" name="price_cents" required></label><button class="primary">Salvar opção</button></form><?php $this->adminTable($addons,['name','min_choices','max_choices','required','option_count','active']);endif;?>
        <?php if($page==='settings'):?><form class="settings" method="post" action="/admin/action"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><input type="hidden" name="action" value="settings"><label>Nome da loja<input name="store_name" value="Burguer App"></label><label>Pedido mínimo em centavos<input type="number" name="minimum_order_cents" value="2000"></label><button class="primary">Salvar configurações</button></form><?php endif;?>
        </main></div><?php $this->layout('Painel administrativo',ob_get_clean(),true);
    }

    private function adminAction(): never
    {
        Auth::requireAdmin();$action=$_POST['action']??'';
        if($action==='order_status'){
            $status=$_POST['status']??'';$id=(int)($_POST['id']??0);
            $isKanban=strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH']??'','XMLHttpRequest')===0;
            try {
                Database::transaction(function(PDO$db)use($id,$status){
                    $s=$db->prepare('SELECT status FROM orders WHERE id=:id FOR UPDATE');$s->execute(['id'=>$id]);$old=$s->fetchColumn();
                    if(!$old)throw new \DomainException('Pedido inexistente');
                    $flow=['received'=>['confirmed','rejected','cancelled'],'confirmed'=>['preparing','cancelled'],'preparing'=>['out_for_delivery','cancelled'],'out_for_delivery'=>['delivered','cancelled'],'delivered'=>[],'cancelled'=>[],'rejected'=>[]];
                    if(!in_array($status,$flow[$old]??[],true))throw new \DomainException('Transição de status não permitida');
                    $update=$db->prepare('UPDATE orders SET status=:s WHERE id=:id AND status=:old');$update->execute(['s'=>$status,'id'=>$id,'old'=>$old]);
                    if($update->rowCount()!==1)throw new \DomainException('O pedido foi atualizado por outro usuário');
                    $db->prepare('INSERT INTO order_status_history(order_id,actor_id,old_status,new_status)VALUES(:o,:a,:old,:new)')->execute(['o'=>$id,'a'=>Auth::user()['id'],'old'=>$old,'new'=>$status]);
                });
                $this->audit('order.status','order',(string)$id,['status'=>$status]);
                if($isKanban){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'status'=>$status],JSON_THROW_ON_ERROR);exit;}
                $this->flash('Status atualizado.','success','/admin?page=orders');
            } catch(\DomainException $e) {
                if($isKanban){http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_THROW_ON_ERROR);exit;}
                $this->flash($e->getMessage(),'error','/admin?page=orders');
            }
        }
        if($action==='product_toggle'){$id=(int)($_POST['id']??0);$this->db->prepare('UPDATE products SET active=NOT active WHERE id=:id')->execute(['id'=>$id]);$this->audit('product.toggle','product',(string)$id,[]);$this->flash('Produto atualizado.','success','/admin?page=products');}
        if($action==='product_create'){$name=trim($_POST['name']??'');$description=trim($_POST['description']??'');$price=(int)($_POST['price_cents']??0);$category=(int)($_POST['category_id']??0);if($name===''||$description===''||$price<1)$this->flash('Preencha os dados do produto.','error','/admin?page=products');$image=$this->secureImageUpload($_FILES['image']??null)??'/assets/house-bacon.jpg';$slug=$this->slug($name).'-'.substr(bin2hex(random_bytes(3)),0,6);$s=$this->db->prepare('INSERT INTO products(category_id,name,slug,description,price_cents,image_path)VALUES(:c,:n,:slug,:d,:p,:img)');$s->execute(['c'=>$category,'n'=>mb_substr($name,0,140),'slug'=>$slug,'d'=>mb_substr($description,0,500),'p'=>$price,'img'=>$image]);$this->audit('product.create','product',$this->db->lastInsertId(),[]);$this->flash('Produto cadastrado.','success','/admin?page=products');}
        if($action==='category_create'){$name=trim($_POST['name']??'');if($name==='')$this->flash('Informe o nome.','error','/admin?page=categories');$s=$this->db->prepare('INSERT INTO categories(name,slug,sort_order)VALUES(:n,:slug,:sort)');$s->execute(['n'=>mb_substr($name,0,100),'slug'=>$this->slug($name).'-'.substr(bin2hex(random_bytes(2)),0,4),'sort'=>(int)($_POST['sort_order']??0)]);$this->flash('Categoria criada.','success','/admin?page=categories');}
        if($action==='coupon_create'){$code=mb_strtoupper(trim($_POST['code']??''));$value=(int)($_POST['value']??0);if(!preg_match('/^[A-Z0-9_-]{3,40}$/',$code)||$value<1||$value>100)$this->flash('Cupom inválido.','error','/admin?page=coupons');$s=$this->db->prepare("INSERT INTO coupons(code,type,value,min_order_cents,usage_limit)VALUES(:code,'percent',:v,:min,:lim)");$s->execute(['code'=>$code,'v'=>$value,'min'=>(int)($_POST['min_order_cents']??0),'lim'=>(int)($_POST['usage_limit']??0)?:null]);$this->flash('Cupom criado.','success','/admin?page=coupons');}
        if($action==='area_create'){$name=trim($_POST['name']??'');$fee=(int)($_POST['fee_cents']??-1);if($name===''||$fee<0)$this->flash('Área inválida.','error','/admin?page=areas');$s=$this->db->prepare('INSERT INTO delivery_areas(name,fee_cents,min_order_cents,eta_minutes)VALUES(:n,:f,:m,:eta)');$s->execute(['n'=>mb_substr($name,0,120),'f'=>$fee,'m'=>(int)($_POST['min_order_cents']??0),'eta'=>max(10,(int)($_POST['eta_minutes']??45))]);$this->flash('Área criada.','success','/admin?page=areas');}
        if($action==='banner_create'){$title=trim($_POST['title']??'');$image=$this->secureImageUpload($_FILES['image']??null);$url=trim($_POST['destination_url']??'');if($title===''||!$image||($url!==''&&!str_starts_with($url,'/'))) $this->flash('Campanha inválida. Use apenas destinos internos iniciados por /.','error','/admin?page=banners');$s=$this->db->prepare('INSERT INTO banners(title,body,image_path,destination_url,sort_order)VALUES(:t,:b,:i,:u,:o)');$s->execute(['t'=>mb_substr($title,0,140),'b'=>mb_substr(trim($_POST['body']??''),0,300),'i'=>$image,'u'=>mb_substr($url,0,255),'o'=>(int)($_POST['sort_order']??0)]);$this->audit('banner.create','banner',$this->db->lastInsertId(),[]);$this->flash('Campanha criada.','success','/admin?page=banners');}
        if($action==='addon_group_create'){$name=trim($_POST['name']??'');$min=max(0,(int)($_POST['min_choices']??0));$max=max($min,(int)($_POST['max_choices']??1));if($name==='')$this->flash('Grupo inválido.','error','/admin?page=addons');$s=$this->db->prepare('INSERT INTO addon_groups(name,min_choices,max_choices,required)VALUES(:n,:min,:max,:req)');$s->execute(['n'=>mb_substr($name,0,120),'min'=>$min,'max'=>$max,'req'=>$min>0]);$this->flash('Grupo criado.','success','/admin?page=addons');}
        if($action==='addon_create'){$name=trim($_POST['name']??'');$group=(int)($_POST['group_id']??0);$price=(int)($_POST['price_cents']??-1);if($name===''||$group<1||$price<0)$this->flash('Opção inválida.','error','/admin?page=addons');$s=$this->db->prepare('INSERT INTO addons(group_id,name,price_cents)VALUES(:g,:n,:p)');$s->execute(['g'=>$group,'n'=>mb_substr($name,0,120),'p'=>$price]);$this->flash('Opção criada.','success','/admin?page=addons');}
        if($action==='user_create'){$name=trim($_POST['name']??'');$email=mb_strtolower(trim($_POST['email']??''));$pass=$_POST['password']??'';if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<12)$this->flash('Dados de usuário inválidos.','error','/admin?page=users');$s=$this->db->prepare("INSERT INTO users(name,email,password_hash,role)VALUES(:n,:e,:h,'manager')");$s->execute(['n'=>mb_substr($name,0,120),'e'=>$email,'h'=>password_hash($pass,PASSWORD_ARGON2ID)]);$this->audit('user.create','user',$this->db->lastInsertId(),['role'=>'manager']);$this->flash('Usuário criado.','success','/admin?page=users');}
        if($action==='settings'){foreach(['store_name','minimum_order_cents']as$k){$v=mb_substr(trim($_POST[$k]??''),0,500);$this->db->prepare('INSERT INTO settings(setting_key,setting_value)VALUES(:k,:v)ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute(['k'=>$k,'v'=>$v]);}$this->audit('settings.update','settings',null,[]);$this->flash('Configurações salvas.','success','/admin?page=settings');}
        $this->flash('Ação inválida.','error','/admin');
    }

    private function adminTable(array $rows,array $fields): void { ?><section class="table-wrap"><table><thead><tr><?php foreach($fields as$f):?><th><?=Security::e($f)?></th><?php endforeach;?></tr></thead><tbody><?php foreach($rows as$r):?><tr><?php foreach($fields as$f):?><td><?=Security::e($r[$f]??'')?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></section><?php }

    private function serveUpload(string $name):never{$path=dirname(__DIR__).'/storage/uploads/'.$name;if(!is_file($path)){http_response_code(404);exit;}$ext=pathinfo($name,PATHINFO_EXTENSION);header('Content-Type: '.['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$ext]);header('Cache-Control: public, max-age=31536000, immutable');header('Content-Disposition: inline; filename="image.'.$ext.'"');readfile($path);exit;}

    private function createForm(string $action,string $title,array $fields):string{ob_start();?><form class="settings" method="post" action="/admin/action"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><input type="hidden" name="action" value="<?=Security::e($action)?>"><h2><?=Security::e($title)?></h2><?php foreach($fields as[$name,$label,$type]):?><label><?=Security::e($label)?><input type="<?=Security::e($type)?>" name="<?=Security::e($name)?>" required></label><?php endforeach;?><button class="primary">Salvar</button></form><?php return ob_get_clean();}

    private function secureImageUpload(?array $file):?string{if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;if($file['error']!==UPLOAD_ERR_OK||$file['size']>5_000_000)throw new \DomainException('Upload inválido');$info=@getimagesize($file['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!$info||!isset($allowed[$info['mime']])||$info[0]>4000||$info[1]>4000)throw new \DomainException('Imagem inválida');$name=bin2hex(random_bytes(16)).'.'.$allowed[$info['mime']];$dir=dirname(__DIR__).'/storage/uploads';if(!is_dir($dir))mkdir($dir,0750,true);if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))throw new \RuntimeException('Falha no upload');return '/uploads/'.$name;}

    private function slug(string $value):string{$value=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;$value=strtolower(preg_replace('/[^a-zA-Z0-9]+/','-',$value)??'');return trim($value,'-')?:'item';}

    private function audit(string $action,string $entity,?string $id,array $metadata):void{$ip=$_SERVER['REMOTE_ADDR']??'';$hash=hash_hmac('sha256',$ip,Config::get('APP_KEY','local-development-key'));$s=$this->db->prepare('INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,metadata_json,ip_hash)VALUES(:a,:action,:e,:id,:m,:ip)');$s->execute(['a'=>Auth::user()['id']??null,'action'=>$action,'e'=>$entity,'id'=>$id,'m'=>json_encode($metadata,JSON_THROW_ON_ERROR),'ip'=>$hash]);}

    private function layout(string $title,string $content,bool $admin=false):void{$flash=$_SESSION['flash']??null;unset($_SESSION['flash']);$cartCount=0;foreach(($_SESSION['cart']??[])as$line)$cartCount+=is_array($line)?(int)($line['quantity']??0):(int)$line;$whatsapp=preg_replace('/\D/','',Config::get('WHATSAPP_NUMBER',''));?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=Security::e($title)?></title><link rel="stylesheet" href="/assets/app.css"></head><body class="<?=$admin?'admin-body':''?>"><?php if(!$admin):?><header class="topbar"><a href="/" class="brand"><img src="/assets/logo-burguer-app.jpg"><b>BURGUER APP</b></a><nav><a href="/">Início</a><a href="/#cardapio">Cardápio</a><a href="/#promocoes">Promoções</a><a href="/#duvidas">Dúvidas</a><a class="cart-link" href="/checkout" aria-label="Abrir sacola"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8.5h11l1.2 11H5.3l1.2-11Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg><span>Sacola (<?=$cartCount?>)</span></a><?php if(Auth::check()):?><form method="post" action="/logout"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><button>Sair</button></form><?php else:?><a href="/login">Entrar</a><?php endif;?></nav></header><?php endif;?><?php if($flash):?><div class="toast <?=Security::e($flash['type'])?>"><?=Security::e($flash['message'])?></div><?php endif;?><?=$content?><?php if(!$admin && $whatsapp!==''):?><a class="whatsapp" href="https://wa.me/<?=Security::e($whatsapp)?>" rel="noopener noreferrer" target="_blank"><img src="/assets/whatsapp.svg" alt=""><span>Fale com a Burguer</span></a><?php endif;?><script src="/assets/app.js" defer></script></body></html><?php }
    private function flash(string $message,string $type,string $path):never{$_SESSION['flash']=['message'=>$message,'type'=>$type];Auth::redirect($path);}
    private static function money(int $cents):string{return 'R$ '.number_format($cents/100,2,',','.');}
    private static function statusLabel(string $s):string{return ['received'=>'Recebido','confirmed'=>'Confirmado','preparing'=>'Em preparação','out_for_delivery'=>'Saiu para entrega','delivered'=>'Entregue','cancelled'=>'Cancelado','rejected'=>'Recusado'][$s]??$s;}
}

