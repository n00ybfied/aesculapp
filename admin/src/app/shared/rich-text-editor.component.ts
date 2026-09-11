import { AfterViewInit, Component, ElementRef, OnDestroy, effect, model, signal, viewChild } from '@angular/core';
import { Editor } from '@tiptap/core';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import { MediaPickerComponent, PickedMedia } from './media-picker.component';

@Component({
  selector: 'app-rich-text-editor',
  imports: [MediaPickerComponent],
  template: `
    <div class="toolbar" aria-label="Textformatierung">
      <button type="button" [attr.aria-pressed]="active('bold')" (click)="editor?.chain().focus().toggleBold().run()">Fett</button>
      <button type="button" [attr.aria-pressed]="active('italic')" (click)="editor?.chain().focus().toggleItalic().run()">Kursiv</button>
      <button type="button" [attr.aria-pressed]="active('heading')" (click)="editor?.chain().focus().toggleHeading({level: 2}).run()">Überschrift</button>
      <button type="button" [attr.aria-pressed]="active('bulletList')" (click)="editor?.chain().focus().toggleBulletList().run()">Liste</button>
      <button type="button" [attr.aria-pressed]="active('orderedList')" (click)="editor?.chain().focus().toggleOrderedList().run()">Nummeriert</button>
      <button type="button" (click)="link()">Link</button>
      <app-media-picker (selected)="image($event)" />
      @if(active('image')) { <button type="button" (click)="editor?.chain().focus().deleteSelection().run()">Bild entfernen</button> }
      <button type="button" aria-label="Rückgängig" (click)="editor?.chain().focus().undo().run()">↶</button>
      <button type="button" aria-label="Wiederholen" (click)="editor?.chain().focus().redo().run()">↷</button>
    </div>
    @if(error()) { <p role="alert">{{error()}}</p> }
    <p class="image-hint">Bildgröße ändern: Bild anklicken und an einer Ecke ziehen. Die Proportionen bleiben erhalten.</p>
    <div #host class="writing-area"></div>
  `,
  styles: [`
    :host{display:block;border:1px solid var(--admin-border);border-radius:.6rem;overflow:hidden;background:var(--admin-surface)}
    .toolbar{display:flex;flex-wrap:wrap;gap:.3rem;padding:.6rem;background:var(--admin-background);border-bottom:1px solid var(--admin-border)}
    button{min-height:2.75rem;padding:.4rem .65rem;border:1px solid var(--admin-border);border-radius:.35rem;background:var(--admin-surface);color:var(--admin-text);cursor:pointer}
    .image-hint{margin:0;padding:.6rem 1rem;font-size:.8rem;color:var(--admin-muted)}
    button[aria-pressed=true]{background:var(--admin-primary);color:white}
    button:focus-visible{outline:3px solid var(--admin-primary);outline-offset:2px}
    :host ::ng-deep .ProseMirror{min-height:18rem;padding:1rem;outline:none;line-height:1.65;overflow-wrap:anywhere}
    :host:focus-within{border-color:var(--admin-primary)}
    :host ::ng-deep .ProseMirror img{display:block;max-width:100%;height:auto!important}
    :host ::ng-deep [data-resize-container]{max-width:100%;margin:1rem 0}
    :host ::ng-deep [data-resize-wrapper]{max-width:100%;min-width:0}
    :host ::ng-deep [data-resize-handle]{width:24px;height:24px;z-index:1;touch-action:none;opacity:0}
    :host ::ng-deep [data-resize-handle]::after{content:'';position:absolute;inset:5px;border:2px solid var(--admin-primary);border-radius:3px;background:var(--admin-surface)}
    :host ::ng-deep [data-resize-container]:hover [data-resize-handle],
    :host ::ng-deep [data-resize-container].ProseMirror-selectednode [data-resize-handle],
    :host ::ng-deep [data-resize-container][data-resize-state=true] [data-resize-handle]{opacity:1}
    :host ::ng-deep [data-resize-handle=top-left]{transform:translate(-50%,-50%);cursor:nwse-resize}
    :host ::ng-deep [data-resize-handle=top-right]{transform:translate(50%,-50%);cursor:nesw-resize}
    :host ::ng-deep [data-resize-handle=bottom-left]{transform:translate(-50%,50%);cursor:nesw-resize}
    :host ::ng-deep [data-resize-handle=bottom-right]{transform:translate(50%,50%);cursor:nwse-resize}
    :host ::ng-deep [data-resize-container].ProseMirror-selectednode{outline:none}
    :host ::ng-deep .ProseMirror-selectednode:not([data-resize-container]),
    :host ::ng-deep [data-resize-container].ProseMirror-selectednode > [data-resize-wrapper] > img{outline:3px solid var(--admin-primary)}
    :host ::ng-deep .ProseMirror p{margin:.5rem 0}
  `]
})
export class RichTextEditorComponent implements AfterViewInit, OnDestroy {
  readonly value = model('');
  private readonly host = viewChild.required<ElementRef<HTMLElement>>('host');
  protected editor: Editor | null = null;
  protected readonly revision = signal(0);
  protected readonly error = signal('');
  constructor() { effect(() => { const value = this.value(); if(this.editor && value !== this.editor.getHTML()) this.editor.commands.setContent(value, {emitUpdate:false}); }); }
  ngAfterViewInit(): void {
    this.editor = new Editor({
      element:this.host().nativeElement,
      extensions:[StarterKit.configure({link:false,heading:{levels:[2,3]}}), Link.configure({openOnClick:false,defaultProtocol:'https://'}),Image.configure({allowBase64:false,resize:{enabled:true,minWidth:120,minHeight:80,alwaysPreserveAspectRatio:true}})],
      content:this.value(),
      editorProps:{attributes:{'aria-label':'Beschreibung','role':'textbox','aria-multiline':'true'}},
      onUpdate:({editor})=>this.value.set(editor.getHTML()),
      onTransaction:()=>this.revision.update(v=>v+1),
    });
  }
  ngOnDestroy():void { this.editor?.destroy(); }
  protected active(name:string):boolean { this.revision(); return this.editor?.isActive(name) ?? false; }
  protected image(image:PickedMedia):void { this.editor?.chain().focus().setImage({src:image.url,alt:image.name}).run(); }
  protected link():void {
    const url=prompt('Link-Adresse (https://… oder mailto:…)', String(this.editor?.getAttributes('link')['href'] ?? ''));
    if(url===null)return;
    if(!url.trim()){this.editor?.chain().focus().extendMarkRange('link').unsetLink().run();return;}
    if(!/^(https?:\/\/|mailto:)/i.test(url.trim())){this.error.set('Bitte eine gültige Link-Adresse eingeben.');return;}
    this.error.set('');this.editor?.chain().focus().extendMarkRange('link').setLink({href:url.trim()}).run();
  }
}
