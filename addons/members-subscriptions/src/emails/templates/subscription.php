<?php
/**
 * Subscription email template
 * 
 * Available variables:
 * - {content}: Email content
 * - subscription: Subscription object
 * - user: User object
 * - product: Product object
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?php echo wp_specialchars_decode(get_option('blogname'), ENT_QUOTES); ?></title>
    <style type="text/css">
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, 'Lucida Grande', sans-serif;
            line-height: 1.6;
            color: #333333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2, h3, h4, h5, h6 {
            margin-top: 20px;
            margin-bottom: 10px;
            line-height: 1.2;
            color: #222;
        }
        h1 {
            font-size: 24px;
        }
        h2 {
            font-size: 20px;
        }
        h3 {
            font-size: 18px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        p {
            margin-top: 0;
            margin-bottom: 15px;
        }
        a {
            color: #0073aa;
            text-decoration: underline;
        }
        .header {
            padding: 20px 0;
            border-bottom: 1px solid #eee;
            text-align: center;
        }
        .header img {
            max-width: 200px;
            height: auto;
        }
        .content {
            padding: 20px 0;
        }
        .footer {
            padding: 20px 0;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #888;
            text-align: center;
        }
        .button {
            display: inline-block;
            background-color: #0073aa;
            color: #ffffff !important;
            font-weight: bold;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin: 15px 0;
        }
        ul {
            padding-left: 20px;
        }
        li {
            margin-bottom: 8px;
        }
        .subscription-details {
            background-color: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo wp_specialchars_decode(get_option('blogname'), ENT_QUOTES); ?></h1>
    </div>
    
    <div class="content">
        <?php echo '{content}'; ?>
    </div>
    
    <div class="footer">
        <p>
            <?php echo wp_specialchars_decode(get_option('blogname'), ENT_QUOTES); ?> &copy; <?php echo date('Y'); ?>
            <br>
            <a href="<?php echo get_home_url(); ?>"><?php echo get_home_url(); ?></a>
        </p>
    </div>
</body>
</html>