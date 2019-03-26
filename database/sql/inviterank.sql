CREATE TABLE `customer_invite_rank`
(
  `id`         int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `user_id`    int(11) unsigned NOT NULL COMMENT '用户ID',
  `count`      int(1) NOT NULL DEFAULT '0' COMMENT '粉丝数量',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4 COMMENT ='粉丝数量排行榜'