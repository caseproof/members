<?php
if( ! defined( 'ABSPATH' ) ) { die( 'You are not allowed to call this page directly.' ); }

$links_count = count( $links );
?>

<div class="members-footer-promotion">
  <p><?php echo esc_html( $title ); ?>

  <ul class="members-footer-promotion-links">
    <?php foreach( $links as $key => $item ): ?>
      <li>
        <?php
        $attributes = array(
          'href' => esc_url( $item['url'] ),
          'target' => isset( $item['target'] ) ? $item['target'] : false,
          'rel' => isset( $item['target'] ) ? 'noopener noreferrer' : false
        );

        $attribute_str = '';

        foreach( $attributes as $attr_key => $attr_item ) {
          if( $attr_item ) {
            $attribute_str .= sprintf( '%s="%s"', $attr_key, esc_attr( $attr_item ) );
          }
        }

        printf(
          '<a %1$s>%2$s</a>%3$s',
          $attribute_str,
          esc_html( $item['text'] ),
          $links_count === $key + 1 ? '' : '<span>/</span>'
        );
        ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <ul class="members-footer-promotion-social">
    <li>
      <a href="https://facebook.com/memberpress" target="_blank" rel="noopener noreferrer">
        <svg width="16" height="16" aria-hidden="true">
          <path fill="#A7AAAD" d="M16 8.05A8.02 8.02 0 0 0 8 0C3.58 0 0 3.6 0 8.05A8 8 0 0 0 6.74 16v-5.61H4.71V8.05h2.03V6.3c0-2.02 1.2-3.15 3-3.15.9 0 1.8.16 1.8.16v1.98h-1c-1 0-1.31.62-1.31 1.27v1.49h2.22l-.35 2.34H9.23V16A8.02 8.02 0 0 0 16 8.05Z"/>
        </svg>
        <span class="screen-reader-text"><?php echo esc_html('Facebook'); ?></span>
      </a>
    </li>
    <li>
      <a href="https://www.instagram.com/memberpress" target="_blank" rel="noopener noreferrer">
        <svg width="16" height="16" aria-hidden="true">
          <path fill="#A7AAAD" d="M8.016 4.39c-2 0-3.594 1.626-3.594 3.594 0 2 1.594 3.594 3.594 3.594a3.594 3.594 0 0 0 3.593-3.594c0-1.968-1.625-3.593-3.593-3.593Zm0 5.938a2.34 2.34 0 0 1-2.344-2.344c0-1.28 1.031-2.312 2.344-2.312a2.307 2.307 0 0 1 2.312 2.312c0 1.313-1.031 2.344-2.312 2.344Zm4.562-6.062a.84.84 0 0 0-.844-.844.84.84 0 0 0-.843.844.84.84 0 0 0 .843.843.84.84 0 0 0 .844-.843Zm2.375.843c-.062-1.125-.312-2.125-1.125-2.937-.812-.813-1.812-1.063-2.937-1.125-1.157-.063-4.625-.063-5.782 0-1.125.062-2.093.312-2.937 1.125-.813.812-1.063 1.812-1.125 2.937-.063 1.157-.063 4.625 0 5.782.062 1.125.312 2.093 1.125 2.937.844.813 1.812 1.063 2.937 1.125 1.157.063 4.625.063 5.782 0 1.125-.062 2.125-.312 2.937-1.125.813-.844 1.063-1.812 1.125-2.937.063-1.157.063-4.625 0-5.782Zm-1.5 7c-.219.625-.719 1.094-1.312 1.344-.938.375-3.125.281-4.125.281-1.032 0-3.22.094-4.125-.28a2.37 2.37 0 0 1-1.344-1.345c-.375-.906-.281-3.093-.281-4.125 0-1-.094-3.187.28-4.125a2.41 2.41 0 0 1 1.345-1.312c.906-.375 3.093-.281 4.125-.281 1 0 3.187-.094 4.125.28.593.22 1.062.72 1.312 1.313.375.938.281 3.125.281 4.125 0 1.032.094 3.22-.28 4.125Z"/>
        </svg>
        <span class="screen-reader-text"><?php echo esc_html('Instagram'); ?></span>
      </a>
    </li>
    <li>
      <a href="https://twitter.com/memberpress" target="_blank" rel="noopener noreferrer">
        <svg width="17" height="16" aria-hidden="true">
          <path fill="#A7AAAD" d="M15.27 4.43A7.4 7.4 0 0 0 17 2.63c-.6.27-1.3.47-2 .53a3.41 3.41 0 0 0 1.53-1.93c-.66.4-1.43.7-2.2.87a3.5 3.5 0 0 0-5.96 3.2 10.14 10.14 0 0 1-7.2-3.67C.86 2.13.7 2.73.7 3.4c0 1.2.6 2.26 1.56 2.89a3.68 3.68 0 0 1-1.6-.43v.03c0 1.7 1.2 3.1 2.8 3.43-.27.06-.6.13-.9.13a3.7 3.7 0 0 1-.66-.07 3.48 3.48 0 0 0 3.26 2.43A7.05 7.05 0 0 1 0 13.24a9.73 9.73 0 0 0 5.36 1.57c6.42 0 9.91-5.3 9.91-9.92v-.46Z"/>
        </svg>
        <span class="screen-reader-text"><?php echo esc_html('Twitter'); ?></span>
      </a>
    </li>
  </ul>
</div>
