<?php

namespace
{
	
	class Form implements \Iterator, \Countable, \ArrayAccess
	{
		private $name;
		protected $fields = [];
		private $keys = [];
		
		private $pos = 0;
		private $count = 0;
		
		public function __construct($name)
		{
			$this->name = $name;
		}
		
		public function getName()
		{
			return $this->name;
		}
		
		
		public function getAction()
		{
			return 'action/' . $this->getName();
		}

		public function add(\form\Field $field)
		{
			$this->fields[$field->getName()] = $field;
			$field->setForm($this);
		}

		public function getField($name)
		{
			return $this->fields[$name];
		}
		
		public function html($layout)
		{
			$html = '';
			foreach ($this->fields as $field)
				$html .= $layout::formatField($field->html());
			
			return $html;
		}

		public static function process()
		{
			if (isset($_SESSION['form'], $_SESSION['form']['display']) && $_SESSION['form']['display'] == 'modal')
			{ ?>
				<script type="text/javascript">
					window.addLoadEvent(function () { getForm('<?php echo $_SESSION['form']['name']; ?>'); });
				</script>
				<?php
			}
		}

		public static function loadForm($name)
		{
			$filename = PART_PATH . '/forms/' . $name . '.form.php';
			if (file_exists($filename))
			{
				include($filename);
			}
			else
			{
				echo 'form <' . $name . '> introuvable';
			}
		}
		
		public static function loadDef($name, $options = null)
		{
			$filename = PART_PATH . '/forms/def/' . $name . '.def.php';
			if (file_exists($filename))
			{
				$form = include($filename);
				$form->postLoadDef($options);
				return $form;
			}
			else
			{
				echo 'def form <' . $name . '> introuvable';
				return null;
			}
		}
		
		public function hasError($props = null, $forceSave = false)
		{
			$hasError = false;
			$fields = [];
			foreach ($this->fields as $field)
			{
				$error = $field->getError();
				$fields[$field->getName()] = ['value' => $field->getValue(), 'msg' => $error];
				if (!empty($error))
					$hasError = true;
			}
			
			if ($hasError || $forceSave)
			{
				$form = ['name' => $this->getName(), 'fields' => $fields];
				if ($props != null)
				{
					foreach ($props as $key => $value)
						$form[$key] = $value;
				}
				$_SESSION['form'] = $form;
				return true;
			}
			return false;
		}
		
		
		protected function postLoadDef($options = null)
		{
			if (isset($_SESSION['form']['name']) && $_SESSION['form']['name'] == $this->getName())
			{
				$fieldsTmp = $_SESSION['form']['fields'];
				unset($_SESSION['form']);

				foreach ($this->fields as $field)
				{
					if (isset($fieldsTmp[$field->getName()]['value']))
						$field->setValue($fieldsTmp[$field->getName()]['value']);

					if (isset($fieldsTmp[$field->getName()]['msg']))
						$field->setError($fieldsTmp[$field->getName()]['msg']);
				}
			}
		}
		
		
		public function offsetExists($offset)
		{
			return isset($this->fields[$offset]);
		}

		public function offsetGet($offset)
		{
			return $this->fields[$offset];
		}

		public function offsetSet($offset, $value)
		{
			//if (!($value instanceof \form\Field)) throw new Exception('Wrong type');
			if ($offset != null) throw new Exception('key must be null');
			
			$this->keys[] = $value->getName();
			$this->count++;
			
			$this->fields[$value->getName()] = $value;
			$value->setFormName($this->getName());
		}

		public function offsetUnset($offset)
		{
			throw new Exception('Not authorized operation');
			//unset($this->fields[$offset]);
		}
		
		
		public function count()
		{
			return $this->count;
		}
		
		public function current()
		{
			return $this->fields[$this->key()];
		}

		public function key()
		{
			return $this->keys[$this->pos];
		}

		public function next()
		{
			++$this->pos;
			return $this;
		}

		public function rewind()
		{
			$this->pos = 0;
			return $this;
		}

		public function valid()
		{
			return $this->pos > -1 && $this->pos < $this->count;
		}

	}
	
	class EntityForm extends Form
	{
		private $entityName;
		private $default;
		
		private $subEntities;
		
		public function __construct($name, $entityName, $subEntities = [])
		{
			parent::__construct($name);
			$this->entityName = $entityName;
			$this->subEntities = $subEntities;
		}
		
		public function getEntity()
		{
			$className = $this->entityName;
			$entity = new $className();
			foreach ($this->fields as $name => $field)
			{
				if (property_exists($entity, $name))
				{
					$entity->$name = $field->getValue();
				}
				else if (($posStart = strpos($name, '[')) !== false && ($posEnd = strpos($name, ']')) !== false && $posStart < $posEnd)
				{
					$prop = substr($name, 0, $posStart);
					$subprop = substr($name, $posStart + 1, $posEnd - $posStart - 1);
					if (isset($this->subEntities[$prop]))
					{
						if ($entity->$prop == null)
							$entity->$prop = new $this->subEntities[$prop]();
						
						if (property_exists($entity->$prop, $subprop))
							$entity->$prop->$subprop = $field->getValue();
					}
				}
			}
			return $entity;
		}
		
		public function getAction()
		{
			return 'action/' . $this->prefixAction . '-' . $this->getName();
		}
		
		public function getPrefix()
		{
			return $this->prefixAction;
		}
		
		
		protected function postLoadDef($options = null)
		{
			$default = $options;
			$this->prefixAction = 'add';
			if (isset($_SESSION['form']['name']) && $_SESSION['form']['name'] == $this->getName())
			{
				$fieldsTmp = $_SESSION['form']['fields'];
				unset($_SESSION['form']);

				foreach ($this->fields as $name => $field)
				{
					if (isset($fieldsTmp[$name]['value']))
						$field->setValue($fieldsTmp[$name]['value']);

					if (isset($fieldsTmp[$name]['msg']))
						$field->setError($fieldsTmp[$name]['msg']);
				}
				
				$this->prefixAction = (empty($fieldsTmp['id']['value']) ? 'add' : 'update');
			}
			else if ($default != null)
			{
				foreach ($this->fields as $name => $field)
				{
					if (property_exists($default, $name))
					{
						$field->setValue($default->$name);
					}
					else if (($posStart = strpos($name, '[')) !== false && ($posEnd = strpos($name, ']')) !== false && $posStart < $posEnd)
					{
						$prop = substr($name, 0, $posStart);
						$subprop = substr($name, $posStart + 1, $posEnd - $posStart - 1);
						if (property_exists($default, $prop) && property_exists($default->$prop, $subprop))
						{
							$field->setValue($default->$prop->$subprop);
						}
					}
						
				}
				
				$this->prefixAction = (empty($default->id) ? 'add' : 'update');
			}
		}
	}
}

namespace form
{
	abstract class Field
	{
		private $form;
		private $name; // field name
		private $value = null;
		private $error = null;
		private $validator = null;
		
		public function __construct($name)
		{
			$this->name = $name;
		}
		
		public function setForm($form)
		{
			$this->form = $form;
		}
		
		public function getFormName()
		{
			return $this->form->getName();
		}
		public function getName()
		{
			return $this->name;
		}
		
		protected function buildAttr($name, $value)
		{
			return  $value != null ? ' ' . $name . '="' . $value . '"' : '';
		}
		
		public function setValue($value)
		{
			$this->error = $this->validator != null ? $this->validator->valide($value) : null;
			$this->value = $value;
			return $this;
		}
		
		public function getValue()
		{
			return $this->value;
		}
		
		public function setError($msg)
		{
			$this->error = $msg;
		}
		public function getError()
		{
			return $this->error;
		}
		
		public function withValidator(Validator $validator)
		{
			$this->validator = $validator;
			return $this;
		}
		
		
		public abstract function html();
		
		public function __toString()
		{
			return $this->html();
		}
	}
	
	class InputText extends Field
	{
		protected $type = 'text';
		private $libelle;
		private $default;
		private $advice;
		private $styles;
		
		public function __construct($name, $libelle)
		{
			parent::__construct($name);
			$this->libelle = $libelle;
		}
		
		public function withDefault($default)
		{
			$this->default = $default;
			return $this;
		}
		
		public function withStyles($styles)
		{
			$this->styles = $styles;
			return $this;
		}
		
		public function withAdvice($advice)
		{
			$this->advice = $advice;
			return $this;
		}
		
		public function html()
		{
			$id = 'form-' . $this->getFormName() . '-' . $this->getName();
			
			$attrValue = $this->buildAttr('value', $this->getValue() != null ? $this->getValue() : $this->default);
			$attrStyle = $this->buildAttr('style', $this->styles);
			$attrMsg = $this->buildAttr('data-error', $this->getError());
			$attrAdvice = $this->buildAttr('data-advice', $this->advice);
			
			$html = '<span' . $attrMsg . $attrAdvice . '>' . 
				'<label for="' . $id . '">' . $this->libelle . '</label>' .
				'<input id="' . $id . '" type="' . $this->type . '" name="' . $this->getName() . '"' . $attrValue . $attrStyle. ' />' .
			'</span>';
			return $html;
		}
	}
	
	class InputPass extends InputText
	{
		public function __construct($name, $libelle) {
			parent::__construct($name, $libelle);
			$this->type = 'password';
		}
	}
	
	class Radio extends Field
	{
		protected $default;
		protected $values;
		
		public function __construct($name, $values)
		{
			parent::__construct($name);
			$this->values = $values;
		}
		
		public function getValues()
		{
			return $this->values;
		}
		
		public function withDefault($default)
		{
			$this->default = $default;
			return $this;
		}
		
		public function html() {
			$baseId = 'form-' . $this->getFormName() . '-' . $this->getName() . '-';
			
			$html = ($this->getError() != null ? '<div class="input-radio-error">' . $this->getError() . '<div>' : '');
			foreach ($this->values as $value => $libelle)
			{
				$html .= $this->getSingleHtml($baseId, $value, $libelle);
			}
			return $html;
		}
		
		protected function getSingleHtml($baseId, $value, $libelle)
		{
			$checked = $value == ($this->getValue() != null ? $this->getValue() : $this->default);
			$html = '<input type="radio" id="' . $baseId . $value . '" name="' . $this->getName() . '" value="' . $value . '"' . ($checked ? ' checked="checked"' : '' ) . ' />';
			$html .= ' <label for="' . $baseId . $value . '">' . $libelle . '</label>';
			return $html;
		}
		
		public function htmlByValue($value) {
			if (!isset($this->values[$value]))
				throw new Exception($value . ' unknwon in radio collection ' . $this->getName());
			
			$baseId = 'form-' . $this->getFormName() . '-' . $this->getName() . '-';
			return $this->getSingleHtml($baseId, $value, $this->values[$value]);
		}
	}
	
	class RadioEntities extends Radio
	{
		private $propKey;
		private $propLibelle;
		
		public function __construct($name, $values, $propKey, $propLibelle)
		{
			parent::__construct($name, $values);
			$this->propKey = $propKey;
			$this->propLibelle = $propLibelle;
		}
		
		public function html()
		{
			$baseId = 'form-' . $this->getFormName() . '-' . $this->getName() . '-';
			
			$html = ($this->getError() != null ? '<div class="input-radio-error">' . $this->getError() . '<div>' : '');
			foreach ($this->values as $entity)
			{
				$html .= $this->getSingleHtml($baseId, $entity->{$this->propKey}, $entity->{$this->propLibelle});
			}
			return $html;
		}
	}
	
	class CheckBox extends Field
	{
		private $libelle;
		private $default;
		
		public function __construct($name, $libelle)
		{
			parent::__construct($name);
			$this->libelle = $libelle;
		}
		
		public function withDefault($default)
		{
			$this->default = $default;
			return $this;
		}
		
		public function html()
		{
			$id = 'form-' . $this->getFormName() . '-' . $this->getName();
			$checked = 'true' == ($this->getValue() != null ? $this->getValue() : $this->default);
			$html = '<input type="checkbox" name="' . $this->getName() . '" value="true" id="' . $id . '"' . ($checked ? ' checked="checked"' : '' ) . ' /> ' .
				'<label for="' . $id . '">' . $this->libelle . '</label>';
			return $html;
		}

	}
	
	class Select extends Field
	{
		protected $libelle;
		protected $values;
		protected $default;
		protected $advice;
		protected $styles;
		protected $firstDefaultOption;
		
		public function __construct($name, $libelle, $values)
		{
			parent::__construct($name);
			$this->libelle = $libelle;
			$this->values = $values;
		}
		
		public function withDefault($default)
		{
			$this->default = $default;
			return $this;
		}
		
		public function withAdvice($advice)
		{
			$this->advice = $advice;
			return $this;
		}
		
		public function withStyles($styles)
		{
			$this->styles = $styles;
			return $this;
		}
		
		public function withFirstOption($text)
		{
			$this->firstDefaultOption = $text;
			return $this;
		}
		
		public function html()
		{
			$id = 'form-' . $this->getFormName() . '-' . $this->getName();
			$attrMsg = $this->buildAttr('data-error', $this->getError());
			$attrAdvice = $this->buildAttr('data-advice', $this->advice);
			$attrStyle = $this->buildAttr('style', $this->styles);
			
			$html = '<span' . $attrMsg . $attrAdvice . '>' . 
				'<label for="' . $id . '">' . $this->libelle . '</label>' .
				'<select name="' . $this->getName() . '" id="' . $id . '"' . $attrStyle . '>' . 
					($this->firstDefaultOption != null ? $this->htmlOption('', '') : '') .//$this->htmlOption('--', $this->firstDefaultOption)
					$this->htmlOptions() . 
				'</select>' .
			'</span>';
			return $html;
		}
		
		protected function htmlOptions()
		{
			$html = '';
			foreach ($this->values as $value => $text)
				$html .= $this->htmlOption($value, $text);
			
			return $html;
		}
		
		protected function htmlOption($value, $text)
		{
			$selected = ($value == ($this->getValue() != null ? $this->getValue() : $this->default) ? ' selected="selected"' : '');
			return '<option value="' . $value . '"' . $selected . '>' . $text . '</option>';
		}
	}
	
	class SelectEntities extends Select
	{
		private $propValue;
		private $propText;
		
		public function __construct($name, $libelle, $values, $propValue, $propText)
		{
			parent::__construct($name, $libelle, $values);
			$this->propValue = $propValue;
			$this->propText = $propText;
		}
		
		protected function htmlOptions()
		{
			$html = '';
			foreach ($this->values as $entity)
				$html .= $this->htmlOption($entity->{$this->propValue}, $entity->{$this->propText});
			
			return $html;
		}
	}
	
	class Hidden extends Field
	{
		public function __construct($name)
		{
			parent::__construct($name);
		}
		
		public function html() {
			$id = 'form-' . $this->getFormName() . '-' . $this->getName();
			$attrValue = $this->buildAttr('value', $this->getValue());
			$html = '<input type="hidden" id="' . $id . '" name="' . $this->getName() . '"' . $attrValue . ' />';
			return $html;
		}
	}
	
	
	class InlineLayout
	{
		public static function formatField($html)
		{
			return $html . ' ';
		}
	}
	
	class InlineWithSpaceLayout
	{
		public static function formatField($html)
		{
			return $html . '&nbsp;&nbsp;&nbsp;&nbsp; ';
		}
	}
	
	class ClassicLayout
	{
		public static function formatField($html)
		{
			return '<div>' . $html . '</div>';
		}
	}
	
	
	interface Validator
	{
		function valide($value);
	}
	
	
	class NotEmptyValidator implements Validator
	{
		private $msgIfError;
		
		public function __construct($msgIfError = 'Valeur obligatoire')
		{
			$this->msgIfError = $msgIfError;
		}
		
		public function valide($value)
		{
			if (empty(trim($value)))
				return $this->msgIfError;
			
			return null;
		}

	}
}

?>