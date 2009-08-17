/**
 * Uploader class
 *
 * @author Fredi Machado <fredisoft at gmail dot com>
 * @link http://fredimachado.com.br
 * @date 08/16/2009
 **/
package
{
	import flash.display.*;
	import flash.events.*;
	import flash.external.ExternalInterface;
	import flash.net.*;
	import flash.utils.*;

	public class multiUpload extends Sprite
	{
		private var param:Object = LoaderInfo(parent.loaderInfo).parameters;
		private var counter:Number = 0;
		private var files:Object; // Files to send
		private var fileIDs:Dictionary; // IDs of each file
		private var fileRef:FileReference; // Reference of single file
		private var fileRefList:FileReferenceList; // Multi select file references
		private var vars:URLVariables; // Vars to send to the upload script
		private var active:String = "";

		// Events that can be listened by Javascript
		static public const MOUSE_CLICK:String         = "onMouseClick";		// When clicking the flash button
		static public const SELECTION_CANCEL:String    = "onSelectionCancel";	// When the selection window is closed/canceled
		static public const FILES_SELECT:String        = "onSelected";			// When the user makes a selection
		static public const UPLOAD_START:String        = "onStart";				// When a file upload is started
		static public const UPLOAD_ERROR:String        = "onError";				// When any error occurs
		static public const UPLOAD_PROGRESS:String     = "onProgress";			// Occurs on any progress change
		static public const UPLOAD_CANCEL:String       = "onCancel";			// When a upload is canceled
		static public const UPLOAD_COMPLETE:String     = "onComplete";			// When the upload is completed
		static public const UPLOAD_ALL_COMPLETE:String = "onAllComplete";		// When all uploads from the queue are completed
		static public const UPLOAD_QUEUE_CLEAR:String  = "onClearQueue";		// When the queue is cleared

		public function multiUpload()
		{
			stage.scaleMode = StageScaleMode.NO_SCALE;
			stage.showDefaultContextMenu = false;

			files       = {};
			fileIDs     = new Dictionary();

			stage.addEventListener(MouseEvent.CLICK, btnClick);

			// Register Javascript callbacks
			ExternalInterface.addCallback("startUpload",      startUpload);
			ExternalInterface.addCallback("cancelUpload",     cancelUpload);
			ExternalInterface.addCallback("clearUploadQueue", clearQueue);
			ExternalInterface.addCallback("getFile",          getFile);
			ExternalInterface.addCallback("setData",          setData);
			
			if (!param.scriptData)
				param.scriptData = '';
		}

		private function btnClick(e:MouseEvent):void
		{
			if (active == "")
			{
				// trigger the click event
				triggerJS(e);
				// Open the select window
				select();					
			}
		}

		private function select():Boolean
		{
			var i:int = 0;
			var type:Object;
			var filter:Array = new Array();

			if (param.desc != "" && param.ext != "")
			{
				var descriptions:Array = param.desc.split('|');
				var extensions:Array = param.ext.split('|');
				for (var n = 0; n < descriptions.length; n++)
					filter.push(new FileFilter(descriptions[n], extensions[n]));
			}

			if (param.multi)
			{
				fileRefList = new FileReferenceList();
				fileRefList.addEventListener(Event.SELECT, triggerJS);
				fileRefList.addEventListener(Event.CANCEL, triggerJS);

				return filter.length ? fileRefList.browse(filter) : fileRefList.browse();
			}
			else
			{
				fileRef = new FileReference();
				fileRef.addEventListener(Event.SELECT, triggerJS);
				fileRef.addEventListener(Event.CANCEL, triggerJS);

				return filter.length ? fileRef.browse(filter) : fileRef.browse();
			}
		}

		public function startUpload(continuing:Boolean = false):void
		{
			var id:String;
			var script:String = param.script;
			var file:FileReference;

			if (continuing && objSize(files) == 0)
			{
				triggerJS({
					type: UPLOAD_ALL_COMPLETE
				});
				return;
			}

			if (active != "" || objSize(files) == 0)
				return;

			if (script.substr(0,1) != '/' && script.substr(0,4) != 'http')
				script = param.path + script;

			vars = new URLVariables();
			if (param.scriptData != '')
				vars.decode(unescape(param.scriptData));

			var urlReq:URLRequest = new URLRequest(script);

			urlReq.method = (param.method == "GET") ? URLRequestMethod.GET : URLRequestMethod.POST;
			urlReq.data   = vars;

			id = getNextId();
			file = getFileRef(id);

			param.maxsize = parseInt(param.maxsize);
			if (param.maxsize > 0 && file.size > param.maxsize)
				triggerJS({
					type: "fileSize",
					target: file
				});
			else
			{
				active = id;
				file.upload(urlReq);
			}
		}

		private function getNextId():String
		{
			var id:String;

			for (id in files)
				break;

			return id;
		}

		public function validId(id:String):Boolean
		{
			return id in files;
		}

		private function addFiles(objFiles:Object):Array
		{
			var ret:Array = new Array();
			var i:int = 0;

			if (objFiles is FileReference)
				ret.push(objFiles);
			else if (objFiles is FileReferenceList)
				ret = objFiles.fileList;

			while (i < ret.length)
			{
				addFile(ret[i]);
				i++;
			}

			return ret;
		}

		// Adiciona a referência do arquivo
		private function addFile(file:FileReference):String
		{
			var id:String = String(++counter);

			files[id] = file;
			fileIDs[file] = id;

			file.addEventListener(Event.OPEN, triggerJS);
			file.addEventListener(DataEvent.UPLOAD_COMPLETE_DATA, triggerJS);
			file.addEventListener(ProgressEvent.PROGRESS, triggerJS);
			file.addEventListener(HTTPStatusEvent.HTTP_STATUS, triggerJS);
			file.addEventListener(IOErrorEvent.IO_ERROR, triggerJS);
			file.addEventListener(SecurityErrorEvent.SECURITY_ERROR, triggerJS);

			return id;
		}

		public function cancelUpload(id:String):void
		{
			var file:FileReference = getFileRef(id);

			if (validId(id))
				file.cancel();

			delete files[id];

			if (active == id)
			{
				active = "";
				startUpload(true);
			}

			triggerJS({
				type: UPLOAD_CANCEL,
				target: file
			});
        }

		function clearQueue():void
		{
			for (var id in files)
				cancelUpload(id);

			triggerJS({
				type: UPLOAD_QUEUE_CLEAR
			});
		}

		private function fileId(file:FileReference):String
		{
			if (file in fileIDs)
				return fileIDs[file];
			return null;
		}

		public function getFiles(arrFiles:Array):Array
		{
			var ret:Array = [];
			var i:int = 0;

			while (i < arrFiles.length)
			{
				ret.push(getFileObject(arrFiles[i]));
				i++;
			}

			return ret;
        }

		private function getFileObject(file:FileReference):Object
		{
			return {
				id: fileId(file),
				name: file.name,
				creation: file.creationDate.getTime(),
				modification: file.modificationDate.getTime(),
				size: file.size,
				type: file.type
			};
		}

		public function getFile(id:String):Object
		{
			if (!validId(id))
				return null;

			return getFileObject(getFileRef(id));
        }

		private function getFileRef(id:String):FileReference
		{
			if (validId(id))
				return files[id];
			return null;
		}

		public function setData(variables:String):void
		{
			param.scriptData = variables;
		}

		private function triggerJS(e:Object):void
		{
			var ret:Object;
			var id:String;

			ret = {};

			id = e.target is FileReference ? fileId(e.target) : null;

			if (id)
				ret.id = id;

			switch (e.type)
			{
				case Event.SELECT:
				{
					var fArr:Array;
					ret.type  = FILES_SELECT;
					fArr      = addFiles(e.target);
					ret.files = getFiles(fArr);
					if (param.auto)
						startUpload();
					break;
				}
				case Event.CANCEL:
				{
					ret.type = SELECTION_CANCEL;
					break;
				}
				case Event.OPEN:
				{
					ret.type = UPLOAD_START;
					break;
				}
				case DataEvent.UPLOAD_COMPLETE_DATA:
				{
					ret.type  = UPLOAD_COMPLETE;
					ret.data  = e.data.replace(/\\/g, "\\\\");
					delete files[id];
					active = "";
					startUpload(true);
					break;
				}
				case ProgressEvent.PROGRESS:
				{
					ret.type        = UPLOAD_PROGRESS;
					ret.bytesLoaded = e.bytesLoaded;
					ret.bytesTotal  = e.bytesTotal;
					break;
				}
				case HTTPStatusEvent.HTTP_STATUS:
				{
					ret.type = UPLOAD_ERROR;
					ret.info = e.status;
					break;
				}
				case IOErrorEvent.IO_ERROR:
				{
					ret.type = UPLOAD_ERROR;
					ret.info = e.text;
					break;
				}
				case SecurityErrorEvent.SECURITY_ERROR:
				{
					ret.type = UPLOAD_ERROR;
					ret.info = e.text;
					break;
				}
				case "fileSize":
				{
					ret.type = UPLOAD_ERROR;
					ret.info = "File size exceeded";
					delete files[id];
					startUpload(true);
					break;
				}
				case UPLOAD_CANCEL:
				{
					ret.type = UPLOAD_CANCEL;
					break;
				}
				case UPLOAD_QUEUE_CLEAR:
				{
					ret.type = UPLOAD_QUEUE_CLEAR;
					break;
				}
				case UPLOAD_ALL_COMPLETE:
				{
					ret.type = UPLOAD_ALL_COMPLETE;
					break;
				}
				case MouseEvent.CLICK:
				{
					ret.type = MOUSE_CLICK;
					break;
				}
				default:
				{
					return;
					break;
				}
			}

			ExternalInterface.call(param.id+".op."+ret.type, ret);
		}
		
		private function objSize(obj:Object):Number
		{
			var i:int = 0;
			for (var item in obj)
				i++;
			return i;
		}
	}
}